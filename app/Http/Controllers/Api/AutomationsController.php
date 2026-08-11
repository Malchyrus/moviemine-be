<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\CustomList;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $automations = $request->user()->automations()
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['automations' => $automations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $list = CustomList::query()
            ->where('id', $validated['action']['list_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $list) {
            return response()->json(['error' => 'list not found'], 422);
        }

        $automation = $request->user()->automations()->create([
            'name' => $validated['name'] ?? null,
            'event' => $validated['event'],
            'condition' => $validated['condition'] ?? null,
            'action' => $validated['action'],
            'enabled' => $validated['enabled'] ?? true,
        ]);

        return response()->json($automation, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $automation = $this->own($request->user(), $id);

        if (! $automation) {
            return response()->json(['error' => 'not found'], 404);
        }

        $validated = $this->validatePayload($request);

        if (isset($validated['action']['list_id'])) {
            $list = CustomList::query()
                ->where('id', $validated['action']['list_id'])
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $list) {
                return response()->json(['error' => 'list not found'], 422);
            }
        }

        foreach (['name', 'event', 'condition', 'action', 'enabled'] as $field) {
            if (array_key_exists($field, $validated)) {
                $automation->{$field} = $validated[$field];
            }
        }

        $automation->save();

        return response()->json($automation->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $automation = $this->own($request->user(), $id);

        if (! $automation) {
            return response()->json(['error' => 'not found'], 404);
        }

        $automation->delete();

        return response()->json(['ok' => true]);
    }

    private function own(User $user, int $id): ?Automation
    {
        return Automation::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'event' => ['required', 'in:movie_added,movie_rated,movie_watched'],
            'condition' => ['nullable', 'array'],
            'condition.field' => ['required_with:condition', 'string'],
            'condition.op' => ['required_with:condition', 'string'],
            'condition.value' => ['required_with:condition'],
            'action' => ['required', 'array'],
            'action.type' => ['required', 'in:add_to_list,move_to_list'],
            'action.list_id' => ['required', 'integer'],
            'enabled' => ['nullable', 'boolean'],
        ]);
    }
}
