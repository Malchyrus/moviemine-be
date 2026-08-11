<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Automation extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'event',
        'condition',
        'action',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'condition' => 'array',
            'action' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
