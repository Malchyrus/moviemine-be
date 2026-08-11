<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Movie extends Model
{
    protected $fillable = [
        'tmdb_id',
        'title',
        'poster_path',
        'backdrop_path',
        'release_date',
        'vote_average',
        'media_type',
        'genres',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'vote_average' => 'float',
            'genres' => 'array',
        ];
    }

    public function watchlists(): HasMany
    {
        return $this->hasMany(Watchlist::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
