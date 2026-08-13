<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EpisodeProgress;
use App\Models\Movie;
use App\Models\User;
use App\Models\Watchlist;
use App\Services\LibraryService;
use App\Services\MovieCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EpisodeProgressController extends Controller
{
    /**
     * Toggle a single episode's watched state. Also auto-marks a show as
     * fully watched once every aired episode has been watched, and clears
     * the whole-show flag when an episode is unchecked.
     */
    public function toggle(Request $request, int $tmdbId): JsonResponse
    {
        $validated = $request->validate([
            'media_type' => ['required', 'in:movie,tv'],
            'season_number' => ['required', 'integer', 'min:0'],
            'episode_number' => ['required', 'integer', 'min:1'],
            'watched' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $movie = app(MovieCache::class)->findByTmdb($tmdbId, $validated['media_type']);

        if (! $movie) {
            return response()->json(['error' => 'not found'], 404);
        }

        app(MovieCache::class)->enrichTvSeasons($movie);

        $row = EpisodeProgress::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->where('season_number', $validated['season_number'])
            ->where('episode_number', $validated['episode_number'])
            ->first();

        if ($validated['watched']) {
            if (! $row) {
                EpisodeProgress::query()->create([
                    'user_id' => $user->id,
                    'movie_id' => $movie->id,
                    'season_number' => $validated['season_number'],
                    'episode_number' => $validated['episode_number'],
                    'watched_at' => now(),
                ]);
            }

            $this->maybeMarkShowWatched($user, $movie);
        } else {
            $row?->delete();

            $entry = Watchlist::query()
                ->where('user_id', $user->id)
                ->where('movie_id', $movie->id)
                ->first();

            if ($entry && $entry->watched_at) {
                $entry->watched_at = null;
                $entry->save();
            }
        }

        return response()->json($this->payload($user, $movie));
    }

    private function maybeMarkShowWatched(User $user, Movie $movie): void
    {
        $total = EpisodeProgress::totalEpisodes($movie);

        if ($total <= 0) {
            return;
        }

        $watched = EpisodeProgress::watchedCount($user, $movie);

        if ($watched < $total) {
            return;
        }

        $entry = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->first();

        if ($entry && ! $entry->watched_at) {
            app(LibraryService::class)->setWatched($user, $movie, true);
        }
    }

    private function payload(User $user, Movie $movie): array
    {
        $entry = Watchlist::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->first();

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
            'watched' => (bool) $entry?->watched_at,
            'watched_episodes' => EpisodeProgress::watchedKeys($user, $movie),
            'total_episodes' => EpisodeProgress::totalEpisodes($movie),
            'progress' => EpisodeProgress::watchedCount($user, $movie),
        ];
    }
}
