<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Watchlist;
use App\Services\MovieCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $entries = Watchlist::query()
            ->where('user_id', $user->id)
            ->with('movie')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Watchlist $w) => $this->formatEntry($w));

        return response()->json(['movies' => $entries]);
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
                'entry' => $this->formatEntry($exists),
            ], 409);
        }

        $entry = Watchlist::query()->create([
            'user_id' => $user->id,
            'movie_id' => $movie->id,
            'status' => 'planning',
        ]);

        return response()->json($this->formatEntry($entry->load('movie')), 201);
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
        ]);

        $user = $request->user();
        $movie = app(MovieCache::class)->findByTmdb($tmdbId);

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

        if (array_key_exists('watched', $validated)) {
            $entry->status = $validated['watched'] ? 'completed' : 'planning';
            $entry->watched_at = $validated['watched'] ? now() : null;
        }

        foreach (['status', 'progress', 'favorite', 'rewatch_count', 'rating'] as $field) {
            if (array_key_exists($field, $validated)) {
                $entry->{$field} = $validated[$field];
            }
        }

        $entry->save();

        return response()->json($this->formatEntry($entry->fresh('movie')));
    }

    public function destroy(Request $request, int $tmdbId): JsonResponse
    {
        $user = $request->user();
        $movie = app(MovieCache::class)->findByTmdb($tmdbId);

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

        return response()->json(['ok' => true]);
    }

    /**
     * Shape an entry for the React frontend.
     */
    private function formatEntry(Watchlist $entry): array
    {
        $movie = $entry->movie;

        return [
            'movie' => [
                'id' => $movie->tmdb_id,
                'title' => $movie->title,
                'poster_path' => $movie->poster_path,
                'backdrop_path' => $movie->backdrop_path,
                'vote_average' => $movie->vote_average,
                'release_date' => $movie->release_date?->toDateString(),
            ],
            'watched' => $entry->status === 'completed',
            'rating' => $entry->rating !== null ? (float) $entry->rating : null,
            'addedAt' => $entry->created_at->valueOf(),
            'status' => $entry->status,
            'progress' => $entry->progress,
            'favorite' => (bool) $entry->favorite,
            'rewatch_count' => $entry->rewatch_count,
            'watched_at' => $entry->watched_at?->toISOString(),
        ];
    }
}
