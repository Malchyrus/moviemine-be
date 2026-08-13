<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EpisodeProgress extends Model
{
    protected $fillable = [
        'user_id',
        'movie_id',
        'season_number',
        'episode_number',
        'watched_at',
    ];

    protected function casts(): array
    {
        return [
            'season_number' => 'integer',
            'episode_number' => 'integer',
            'watched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    /**
     * Watched episode keys ("season:episode") for a user's show, ordered.
     */
    public static function watchedKeys(User $user, Movie $movie): array
    {
        return static::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->orderBy('season_number')
            ->orderBy('episode_number')
            ->get()
            ->map(fn (EpisodeProgress $p) => $p->season_number.':'.$p->episode_number)
            ->all();
    }

    public static function watchedCount(User $user, Movie $movie): int
    {
        return static::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->count();
    }

    /**
     * Known total episode count for a show, from stored per-season data
     * (falling back to the number_of_episodes field).
     */
    public static function totalEpisodes(Movie $movie): int
    {
        $seasons = $movie->seasons ?? [];

        if (! empty($seasons)) {
            return (int) collect($seasons)->sum('episode_count');
        }

        return (int) ($movie->number_of_episodes ?? 0);
    }
}
