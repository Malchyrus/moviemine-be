<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomListMovie extends Model
{
    protected $fillable = [
        'list_id',
        'movie_id',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(CustomList::class, 'list_id');
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }
}
