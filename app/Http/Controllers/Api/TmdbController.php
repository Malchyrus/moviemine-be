<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TmdbController extends Controller
{
    private const BASE = 'https://api.themoviedb.org/3';

    private function proxy(string $path, array $params = []): JsonResponse
    {
        $key = config('services.tmdb.key');

        if (! $key) {
            return response()->json(['error' => 'TMDB_API_KEY is not set'], 500);
        }

        $response = Http::withOptions(['verify' => config('services.tmdb.verify_ssl', true)])
            ->get(self::BASE.$path, [
                'api_key' => $key,
                'language' => 'en-US',
                ...$params,
            ]);

        return response()->json($response->json(), $response->status());
    }

    public function trending(): JsonResponse
    {
        return $this->proxy('/trending/movie/week');
    }

    public function popular(): JsonResponse
    {
        return $this->proxy('/movie/popular');
    }

    public function upcoming(): JsonResponse
    {
        return $this->proxy('/movie/upcoming');
    }

    public function topRated(): JsonResponse
    {
        return $this->proxy('/movie/top_rated');
    }

    public function genres(): JsonResponse
    {
        return $this->proxy('/genre/movie/list');
    }

    public function search(Request $request): JsonResponse
    {
        return $this->proxy('/search/movie', [
            'query' => $request->query('q', ''),
            'page' => $request->query('page', 1),
            'include_adult' => 'false',
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->proxy("/movie/{$id}", [
            'append_to_response' => 'credits,recommendations,videos',
        ]);
    }

    public function watchProviders(Request $request, int $id): JsonResponse
    {
        return $this->proxy("/movie/{$id}/watch/providers", [
            'watch_region' => strtoupper($request->query('region', 'US')),
        ]);
    }

    public function watchRegions(): JsonResponse
    {
        return $this->proxy('/watch/providers/regions');
    }
}
