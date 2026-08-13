<?php

namespace App\Services;

use App\Models\Movie;
use Illuminate\Support\Facades\Http;

class MovieCache
{
    /**
     * Find an existing cached movie by tmdb_id and media type, or create one.
     * Expects keys: id, title, poster_path, backdrop_path, release_date, vote_average.
     */
    public function getOrCreate(array $data): Movie
    {
        $mediaType = $data['media_type'] ?? 'movie';

        $movie = Movie::query()
            ->where('tmdb_id', $data['id'])
            ->where('media_type', $mediaType)
            ->first();

        if ($movie) {
            return $movie;
        }

        return Movie::query()->create([
            'tmdb_id' => $data['id'],
            'title' => $data['title'],
            'poster_path' => $data['poster_path'] ?? null,
            'backdrop_path' => $data['backdrop_path'] ?? null,
            'release_date' => $data['release_date'] ?? null,
            'vote_average' => $data['vote_average'] ?? null,
            'media_type' => $mediaType,
            'genres' => $data['genres'] ?? null,
        ]);
    }

    public function findByTmdb(int $tmdbId, string $mediaType = 'movie'): ?Movie
    {
        return Movie::query()
            ->where('tmdb_id', $tmdbId)
            ->where('media_type', $mediaType)
            ->first();
    }

    /**
     * Pull TV details once from TMDB so season/episode totals are cached
     * locally (used for progress derivation and per-episode tracking).
     */
    public function enrichTvSeasons(Movie $movie): void
    {
        if (($movie->media_type ?? 'movie') !== 'tv' || ! $movie->tmdb_id || ! empty($movie->seasons)) {
            return;
        }

        $key = config('services.tmdb.key');

        if (! $key) {
            return;
        }

        $response = Http::withOptions(['verify' => config('services.tmdb.verify_ssl', true)])
            ->get('https://api.themoviedb.org/3/tv/'.$movie->tmdb_id, [
                'api_key' => $key,
                'language' => 'en-US',
            ]);

        if (! $response->ok()) {
            return;
        }

        $data = $response->json();

        $seasons = collect($data['seasons'] ?? [])
            ->filter(fn (array $season) => (int) ($season['season_number'] ?? 0) > 0)
            ->map(fn (array $season) => [
                'season_number' => (int) $season['season_number'],
                'episode_count' => (int) $season['episode_count'],
                'name' => $season['name'] ?? null,
            ])
            ->values()
            ->all();

        $movie->number_of_seasons = $data['number_of_seasons'] ?? null;
        $movie->number_of_episodes = $data['number_of_episodes'] ?? null;
        $movie->seasons = $seasons;
        $movie->save();
    }
}
