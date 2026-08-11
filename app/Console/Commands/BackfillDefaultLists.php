<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LibraryService;
use Illuminate\Console\Command;

class BackfillDefaultLists extends Command
{
    protected $signature = 'lists:backfill';

    protected $description = 'Create the default lists for every existing user';

    public function handle(LibraryService $service): int
    {
        $count = 0;

        User::query()->chunkById(100, function ($users) use ($service, &$count) {
            foreach ($users as $user) {
                $service->ensureDefaultLists($user);
                $count++;
            }
        });

        $this->info("Backfilled default lists for {$count} users.");

        return self::SUCCESS;
    }
}
