<?php

namespace App\Services;

use App\Models\Movie;

class MovieCache
{
    /**
     * Find an existing cached movie by tmdb_id, or create one.
     * Expects keys: id, title, poster_path, backdrop_path, release_date, vote_average.
     */
    public function getOrCreate(array $data): Movie
    {
        $movie = Movie::query()->where('tmdb_id', $data['id'])->first();

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
            'media_type' => $data['media_type'] ?? 'movie',
            'genres' => $data['genres'] ?? null,
        ]);
    }

    public function findByTmdb(int $tmdbId): ?Movie
    {
        return Movie::query()->where('tmdb_id', $tmdbId)->first();
    }
}
