<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomList;
use App\Models\CustomListMovie;
use App\Models\EpisodeProgress;
use App\Models\User;
use App\Models\Watchlist;
use App\Services\LibraryService;
use App\Services\MovieCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        app(LibraryService::class)->ensureDefaultLists($user);

        $entries = Watchlist::query()
            ->where('user_id', $user->id)
            ->with('movie')
            ->orderByDesc('created_at')
            ->get();

        $service = app(LibraryService::class);
        $statuses = $service->statusesOf($user, $entries->pluck('movie_id')->all());

        return response()->json([
            'movies' => $entries->map(fn (Watchlist $w) => $this->formatEntry($user, $w, $statuses)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'poster_path' => ['nullable', 'string'],
            'backdrop_path' => ['nullable', 'string'],
            'release_date' => ['nullable', 'date'],
            'vote_average' => ['nullable', 'numeric', 'between:0,10'],
            'media_type' => ['nullable', 'in:movie,tv'],
            'genres' => ['nullable', 'array'],
            'genres.*.id' => ['integer'],
            'genres.*.name' => ['string'],
        ]);

        $user = $request->user();
        $movie = app(MovieCache::class)->getOrCreate($validated);

        $exists = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->first();

        if ($exists) {
            return response()->json([
                'error' => 'already exists',
                'entry' => $this->formatEntry($user, $exists->load('movie')),
            ], 409);
        }

        $service = app(LibraryService::class);
        $service->ensureDefaultLists($user);

        $prefs = $user->preferencesOrDefault();
        $target = null;

        if ($prefs['default_add_list_id']) {
            $target = CustomList::query()
                ->where('id', $prefs['default_add_list_id'])
                ->where('user_id', $user->id)
                ->first();
        }

        $target ??= $service->defaultList($user, 'planning');

        $service->addToList($user, $movie, $target);

        $entry = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->first();

        return response()->json($this->formatEntry($user, $entry->load('movie')), 201);
    }

    public function update(Request $request, int $tmdbId): JsonResponse
    {
        $validated = $request->validate([
            'watched' => ['nullable', 'boolean'],
            'rating' => ['nullable', 'numeric', 'between:0,10'],
            'status' => ['nullable', 'in:planning,watching,completed,dropped,on_hold'],
            'progress' => ['nullable', 'integer', 'min:0'],
            'favorite' => ['nullable', 'boolean'],
            'rewatch_count' => ['nullable', 'integer', 'min:0'],
            'media_type' => ['nullable', 'in:movie,tv'],
        ]);

        $user = $request->user();
        $movie = app(MovieCache::class)->findByTmdb($tmdbId, $validated['media_type'] ?? 'movie');

        if (! $movie) {
            return response()->json(['error' => 'not found'], 404);
        }

        $entry = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->first();

        if (! $entry) {
            return response()->json(['error' => 'not found'], 404);
        }

        $service = app(LibraryService::class);

        if (array_key_exists('watched', $validated)) {
            $service->setWatched($user, $movie, (bool) $validated['watched']);
        }

        if (array_key_exists('status', $validated)) {
            $service->setStatus($user, $movie, $validated['status']);
            $entry->watched_at = $validated['status'] === 'completed' ? now() : null;
            $entry->save();
        }

        if (array_key_exists('rating', $validated)) {
            $service->setRating($user, $movie, $validated['rating']);
        }

        foreach (['progress', 'favorite', 'rewatch_count'] as $field) {
            if (array_key_exists($field, $validated)) {
                $entry->{$field} = $validated[$field];
            }
        }

        $entry->save();

        return response()->json($this->formatEntry($user, $entry->fresh('movie')));
    }

    public function destroy(Request $request, int $tmdbId): JsonResponse
    {
        $user = $request->user();
        $movie = app(MovieCache::class)->findByTmdb($tmdbId, $request->query('media_type', 'movie'));

        if (! $movie) {
            return response()->json(['error' => 'not found'], 404);
        }

        $deleted = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => 'not found'], 404);
        }

        EpisodeProgress::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->delete();

        CustomListMovie::query()
            ->where('movie_id', $movie->id)
            ->whereHas('list', fn ($q) => $q->where('user_id', $user->id))
            ->delete();

        return response()->json(['ok' => true]);
    }

    private function formatEntry(User $user, Watchlist $entry, array $statuses = []): array
    {
        $service = app(LibraryService::class);
        $movie = $entry->movie;
        $status = $statuses[$entry->movie_id] ?? $service->statusOf($user, $movie);

        return [
            'movie' => [
                'id' => $movie->tmdb_id,
                'title' => $movie->title,
                'poster_path' => $movie->poster_path,
                'backdrop_path' => $movie->backdrop_path,
                'vote_average' => $movie->vote_average,
                'release_date' => $movie->release_date?->toDateString(),
                'genres' => $movie->genres ?? [],
                'media_type' => $movie->media_type ?? 'movie',
                'number_of_seasons' => $movie->number_of_seasons,
                'number_of_episodes' => $movie->number_of_episodes,
            ],
            'watched' => (bool) $entry->watched_at,
            'watched_episodes' => EpisodeProgress::watchedKeys($user, $movie),
            'total_episodes' => EpisodeProgress::totalEpisodes($movie),
            'rating' => $entry->rating !== null ? (float) $entry->rating : null,
            'addedAt' => $entry->created_at->valueOf(),
            'status' => $status,
            'progress' => $entry->progress,
            'favorite' => (bool) $entry->favorite,
            'rewatch_count' => $entry->rewatch_count,
            'watched_at' => $entry->watched_at?->toISOString(),
        ];
    }
}
