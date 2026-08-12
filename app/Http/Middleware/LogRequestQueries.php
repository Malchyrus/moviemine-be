<?php

namespace App\Http\Middleware;

use App\Support\DbMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequestQueries
{
    public function handle(Request $request, Closure $next): Response
    {
        $metrics = app(DbMetrics::class);
        $metrics->reset();

        $start = microtime(true);
        $response = $next($request);
        $wallMs = (microtime(true) - $start) * 1000;

        Log::channel('stderr')->info('db-metrics', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'queries' => $metrics->count,
            'dbMs' => round($metrics->totalMs, 1),
            'wallMs' => round($wallMs, 1),
        ]);

        return $response;
    }
}
