<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Watchlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Personal recommendations built from the user's strongly-liked movies
     * (watched, rated 7+, or favorited). Fetches TMDB "recommendations" for
     * the top few seed movies concurrently, then merges and ranks them.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        $liked = Watchlist::query()
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNotNull('watched_at')
                    ->orWhere('rating', '>=', 7)
                    ->orWhere('favorite', true);
            })
            ->with('movie')
            ->orderByDesc('updated_at')
            ->get();

        $seeds = $liked
            ->map(fn (Watchlist $entry) => [
                'id' => $entry->movie->tmdb_id,
                'weight' => $this->seedWeight($entry),
            ])
            ->filter(fn (array $seed) => $seed['id'] !== null)
            ->sortByDesc('weight')
            ->take(5)
            ->values()
            ->all();

        if (empty($seeds)) {
            return response()->json(['results' => []]);
        }

        $cacheKey = 'recommendations:'.$user->id.':'.implode(',', array_column($seeds, 'id'));

        return response()->json([
            'results' => Cache::remember($cacheKey, now()->addMinutes(20), function () use ($user, $seeds) {
                $key = config('services.tmdb.key');

                if (! $key) {
                    throw new \RuntimeException('TMDB_API_KEY is not set');
                }

                $responses = Http::pool(fn ($pool) => collect($seeds)->map(
                    fn (array $seed) => $pool
                        ->withOptions(['verify' => config('services.tmdb.verify_ssl', true)])
                        ->get(self::BASE."/movie/{$seed['id']}/recommendations", [
                            'api_key' => $key,
                            'language' => 'en-US',
                        ])
                )->all());

                $librarySet = $this->libraryTmdbSet($user->id);
                $scores = [];
                $candidates = [];

                foreach ($responses as $index => $response) {
                    if (! $response || $response->failed() || ! isset($seeds[$index])) {
                        continue;
                    }

                    $seedWeight = $seeds[$index]['weight'];

                    foreach ($response->json('results') ?? [] as $position => $rec) {
                        $id = (int) ($rec['id'] ?? 0);

                        if ($id === 0 || $id === $seeds[$index]['id'] || isset($librarySet[$id])) {
                            continue;
                        }

                        $scores[$id] = ($scores[$id] ?? 0) + $seedWeight / ($position + 2);
                        $candidates[$id] = $rec;
                    }
                }

                arsort($scores);

                $results = [];

                foreach (array_keys($scores) as $id) {
                    $results[] = $candidates[$id];

                    if (count($results) >= 20) {
                        break;
                    }
                }

                return $results;
            }),
        ]);
    }

    private function seedWeight(Watchlist $entry): int
    {
        if ($entry->favorite) {
            return 3;
        }

        if ($entry->rating !== null) {
            return $entry->rating >= 9 ? 3 : 2;
        }

        return 1;
    }

    private function libraryTmdbSet(int $userId): array
    {
        $movieIds = Watchlist::query()->where('user_id', $userId)->pluck('movie_id');

        return Movie::query()
            ->whereIn('id', $movieIds)
            ->pluck('tmdb_id')
            ->flip()
            ->all();
    }
}
