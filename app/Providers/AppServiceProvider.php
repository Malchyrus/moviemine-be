<?php

namespace App\Providers;

use App\Database\NeonPostgresConnector;
use App\Support\DbMetrics;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('db.connector.pgsql', fn () => new NeonPostgresConnector);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->singleton(DbMetrics::class);

        DB::listen(function (QueryExecuted $query): void {
            app(DbMetrics::class)->record($query);
        });

        $this->app->make(ExceptionHandler::class)->renderable(
            function (AuthenticationException $e, Request $request) {
                if ($request->is('api/*')) {
                    return response()->json(['error' => 'Unauthenticated'], 401);
                }

                return null;
            },
        );
    }
}
