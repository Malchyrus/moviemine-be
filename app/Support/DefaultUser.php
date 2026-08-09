<?php

namespace App\Support;

use App\Models\User;

/**
 * Resolves the single demo user used until real authentication is added.
 */
class DefaultUser
{
    public static function get(): User
    {
        $user = User::query()->first();

        if (! $user) {
            $user = User::query()->create([
                'name' => 'Demo User',
                'username' => 'demo',
                'email' => 'demo@cinetrack.app',
                'password' => 'password',
            ]);
        }

        return $user;
    }
}
