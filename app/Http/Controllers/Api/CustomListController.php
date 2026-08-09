<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomList;
use App\Models\CustomListMovie;
use App\Services\MovieCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $lists = CustomList::query()
            ->where('user_id', $user->id)
            ->withCount('movies')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['lists' => $lists]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        $list = CustomList::query()->create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
        ]);

        return response()->json($list, 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $deleted = CustomList::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => 'not found'], 404);
        }

        return response()->json(['ok' => true]);
    }

    public function addMovie(Request $request, int $listId): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'poster_path' => ['nullable', 'string'],
            'backdrop_path' => ['nullable', 'string'],
            'release_date' => ['nullable', 'date'],
            'vote_average' => ['nullable', 'numeric', 'between:0,10'],
        ]);

        $user = $request->user();

        $list = CustomList::query()
            ->where('id', $listId)
            ->where('user_id', $user->id)
            ->first();

        if (! $list) {
            return response()->json(['error' => 'not found'], 404);
        }

        $movie = app(MovieCache::class)->getOrCreate($validated);

        CustomListMovie::query()->firstOrCreate([
            'list_id' => $list->id,
            'movie_id' => $movie->id,
        ]);

        return response()->json($list->loadCount('movies'), 201);
    }

    public function removeMovie(int $listId, int $tmdbId): JsonResponse
    {
        $user = $request->user();

        $list = CustomList::query()
            ->where('id', $listId)
            ->where('user_id', $user->id)
            ->first();

        if (! $list) {
            return response()->json(['error' => 'not found'], 404);
        }

        $movie = app(MovieCache::class)->findByTmdb($tmdbId);

        if ($movie) {
            CustomListMovie::query()
                ->where('list_id', $list->id)
                ->where('movie_id', $movie->id)
                ->delete();
        }

        return response()->json(['ok' => true]);
    }
}
