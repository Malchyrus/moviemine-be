<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomList;
use App\Models\CustomListMovie;
use App\Models\User;
use App\Services\LibraryService;
use App\Services\MovieCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomListController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        app(LibraryService::class)->ensureDefaultLists($user);

        $lists = CustomList::query()
            ->where('user_id', $user->id)
            ->withCount('movies')
            ->with('movies.movie')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return response()->json(['lists' => $lists->map(fn (CustomList $list) => $this->formatList($list))]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        app(LibraryService::class)->ensureDefaultLists($user);

        $list = CustomList::query()->create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_public' => $validated['is_public'] ?? false,
            'position' => ((int) $user->customLists()->max('position')) + 1,
        ]);

        return response()->json($this->formatList($list->load('movies.movie')), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $list = $this->ownList($user, $id);

        if (! $list) {
            return response()->json(['error' => 'not found'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        foreach (['name', 'description', 'is_public'] as $field) {
            if (array_key_exists($field, $validated)) {
                $list->{$field} = $validated[$field];
            }
        }

        $list->save();

        return response()->json($this->formatList($list->load('movies.movie')));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $list = $this->ownList($user, $id);

        if (! $list) {
            return response()->json(['error' => 'not found'], 404);
        }

        if ($list->is_default && $list->type) {
            $preferences = $user->preferences ?? [];
            $deleted = $preferences['deleted_default_types'] ?? [];

            if (! in_array($list->type, $deleted, true)) {
                $deleted[] = $list->type;
                $preferences['deleted_default_types'] = $deleted;
                $user->preferences = $preferences;
                $user->save();
            }
        }

        $list->delete();

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
            'genres' => ['nullable', 'array'],
            'genres.*.id' => ['integer'],
            'genres.*.name' => ['string'],
        ]);

        $user = $request->user();
        $list = $this->ownList($user, $listId);

        if (! $list) {
            return response()->json(['error' => 'not found'], 404);
        }

        $movie = app(MovieCache::class)->getOrCreate($validated);

        app(LibraryService::class)->addToList($user, $movie, $list);

        return response()->json($this->formatList($list->fresh(['movies.movie'])), 201);
    }

    public function move(Request $request, int $listId): JsonResponse
    {
        $validated = $request->validate([
            'tmdb_id' => ['required', 'integer'],
            'from_list_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $target = $this->ownList($user, $listId);

        if (! $target) {
            return response()->json(['error' => 'not found'], 404);
        }

        $movie = app(MovieCache::class)->findByTmdb($validated['tmdb_id']);

        if (! $movie) {
            return response()->json(['error' => 'not found'], 404);
        }

        $from = null;

        if (isset($validated['from_list_id'])) {
            $from = $this->ownList($user, $validated['from_list_id']);
        }

        app(LibraryService::class)->moveToList($user, $movie, $target, $from);

        return response()->json(['ok' => true]);
    }

    public function removeMovie(int $listId, int $tmdbId): JsonResponse
    {
        $user = request()->user();
        $list = $this->ownList($user, $listId);

        if (! $list) {
            return response()->json(['error' => 'not found'], 404);
        }

        $movie = app(MovieCache::class)->findByTmdb($tmdbId);

        if ($movie) {
            app(LibraryService::class)->removeFromList($user, $movie, $list);
        }

        return response()->json($this->formatList($list->fresh(['movies.movie'])));
    }

    private function ownList(User $user, int $id): ?CustomList
    {
        return CustomList::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function formatList(CustomList $list): array
    {
        return [
            'id' => $list->id,
            'name' => $list->name,
            'description' => $list->description,
            'is_public' => $list->is_public,
            'is_default' => $list->is_default,
            'type' => $list->type,
            'position' => $list->position,
            'movies_count' => $list->movies_count ?? $list->movies->count(),
            'movies' => $list->movies
                ->map(fn (CustomListMovie $membership) => $this->formatMovie($membership))
                ->values(),
        ];
    }

    private function formatMovie(CustomListMovie $membership): array
    {
        $movie = $membership->movie;

        return [
            'position' => $membership->position,
            'added_at' => $membership->created_at->valueOf(),
            'id' => $movie->tmdb_id,
            'title' => $movie->title,
            'poster_path' => $movie->poster_path,
            'backdrop_path' => $movie->backdrop_path,
            'vote_average' => $movie->vote_average,
            'release_date' => $movie->release_date?->toDateString(),
            'genres' => $movie->genres ?? [],
        ];
    }
}
