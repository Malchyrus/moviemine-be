<?php

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;

class DbMetrics
{
    public int $count = 0;

    public float $totalMs = 0.0;

    public function record(QueryExecuted $query): void
    {
        $this->count++;
        $this->totalMs += $query->time;
    }

    public function reset(): void
    {
        $this->count = 0;
        $this->totalMs = 0.0;
    }
}
