<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomList extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_public',
        'position',
        'is_default',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_default' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movies(): HasMany
    {
        return $this->hasMany(CustomListMovie::class, 'list_id')->orderBy('position')->orderBy('id');
    }

    public static function defaultTypes(): array
    {
        return [
            'planning' => 'Plan to Watch',
            'watching' => 'Watching',
            'completed' => 'Watched',
            'dropped' => 'Dropped',
            'on_hold' => 'On Hold',
        ];
    }
}
