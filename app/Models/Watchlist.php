<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Watchlist extends Model
{
    protected $fillable = [
        'user_id',
        'movie_id',
        'progress',
        'rating',
        'favorite',
        'rewatch_count',
        'watched_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'favorite' => 'boolean',
            'rewatch_count' => 'integer',
            'progress' => 'integer',
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
}
