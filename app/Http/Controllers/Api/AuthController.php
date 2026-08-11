<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'username' => $validated['username'] ?? null,
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        app(\App\Services\LibraryService::class)->ensureDefaultLists($user);

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('web')->plainTextToken,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('web')->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:users,username,'.$user->id],
            'avatar' => ['sometimes', 'nullable', 'string'],
            'bio' => ['sometimes', 'nullable', 'string'],
            'preferences' => ['sometimes', 'array'],
            'preferences.background' => ['sometimes', 'nullable', 'string'],
            'preferences.auto_move_watched' => ['sometimes', 'boolean'],
            'preferences.default_add_list_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        foreach (['name', 'username', 'avatar', 'bio'] as $field) {
            if (array_key_exists($field, $validated)) {
                $user->{$field} = $validated[$field];
            }
        }

        if (array_key_exists('preferences', $validated)) {
            $user->preferences = array_merge(
                $user->preferencesOrDefault(),
                $validated['preferences'],
            );
        }

        $user->save();

        return response()->json(['user' => $user->fresh()]);
    }
}
