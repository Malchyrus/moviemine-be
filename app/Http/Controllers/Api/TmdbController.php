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

    public function genresTv(): JsonResponse
    {
        return $this->proxy('/genre/tv/list');
    }

    public function trendingAll(): JsonResponse
    {
        return $this->proxy('/trending/all/week');
    }

    public function trendingTv(): JsonResponse
    {
        return $this->proxy('/trending/tv/week');
    }

    public function popularTv(): JsonResponse
    {
        return $this->proxy('/tv/popular');
    }

    public function topRatedTv(): JsonResponse
    {
        return $this->proxy('/tv/top_rated');
    }

    public function airingToday(): JsonResponse
    {
        return $this->proxy('/tv/airing_today');
    }

    public function search(Request $request): JsonResponse
    {
        return $this->proxy('/search/movie', [
            'query' => $request->query('q', ''),
            'page' => $request->query('page', 1),
            'include_adult' => 'false',
        ]);
    }

    public function searchMulti(Request $request): JsonResponse
    {
        return $this->proxy('/search/multi', [
            'query' => $request->query('q', ''),
            'page' => $request->query('page', 1),
            'include_adult' => 'false',
        ]);
    }

    public function searchTv(Request $request): JsonResponse
    {
        return $this->proxy('/search/tv', [
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

    public function tvShow(Request $request, int $id): JsonResponse
    {
        return $this->proxy("/tv/{$id}", [
            'append_to_response' => 'credits,recommendations,videos',
        ]);
    }

    public function tvSeason(Request $request, int $id, int $season): JsonResponse
    {
        return $this->proxy("/tv/{$id}/season/{$season}");
    }

    public function watchProviders(Request $request, int $id): JsonResponse
    {
        return $this->proxy("/movie/{$id}/watch/providers", [
            'watch_region' => strtoupper($request->query('region', 'US')),
        ]);
    }

    public function tvWatchProviders(Request $request, int $id): JsonResponse
    {
        return $this->proxy("/tv/{$id}/watch/providers", [
            'watch_region' => strtoupper($request->query('region', 'US')),
        ]);
    }

    public function watchRegions(): JsonResponse
    {
        return $this->proxy('/watch/providers/regions');
    }

    /**
     * Personal recommendations built from the user's strongly-liked titles
     * (watched, rated 7+, or favorited) across movies and TV shows. Fetches
     * TMDB "recommendations" for the top few seed titles concurrently, then
     * merges and ranks them into one mixed list.
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
                'media_type' => $entry->movie->media_type ?? 'movie',
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

        $cacheKey = 'recommendations:'.$user->id.':'.collect($seeds)
            ->map(fn (array $seed) => $seed['media_type'].':'.$seed['id'])
            ->implode(',');

        return response()->json([
            'results' => Cache::remember($cacheKey, now()->addMinutes(20), function () use ($user, $seeds) {
                $key = config('services.tmdb.key');

                if (! $key) {
                    throw new \RuntimeException('TMDB_API_KEY is not set');
                }

                $responses = Http::pool(fn ($pool) => collect($seeds)->map(
                    fn (array $seed) => $pool
                        ->withOptions(['verify' => config('services.tmdb.verify_ssl', true)])
                        ->get(self::BASE."/{$seed['media_type']}/{$seed['id']}/recommendations", [
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
                    $mediaType = $seeds[$index]['media_type'];

                    foreach ($response->json('results') ?? [] as $position => $rec) {
                        $id = (int) ($rec['id'] ?? 0);
                        $key = $mediaType.':'.$id;

                        if ($id === 0 || $key === $mediaType.':'.$seeds[$index]['id'] || isset($librarySet[$key])) {
                            continue;
                        }

                        $scores[$key] = ($scores[$key] ?? 0) + $seedWeight / ($position + 2);
                        $candidates[$key] = $this->normalizeRec($rec, $mediaType);
                    }
                }

                arsort($scores);

                $results = [];

                foreach (array_keys($scores) as $key) {
                    $results[] = $candidates[$key];

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

    /**
     * Flatten movies and TV shows into one display shape (TMDB TV results use
     * name / first_air_date instead of title / release_date).
     */
    private function normalizeRec(array $rec, string $mediaType): array
    {
        $rec['media_type'] = $mediaType;
        $rec['title'] = $rec['title'] ?? $rec['name'] ?? null;
        $rec['release_date'] = $rec['release_date'] ?? $rec['first_air_date'] ?? null;

        return $rec;
    }

    private function libraryTmdbSet(int $userId): array
    {
        $movieIds = Watchlist::query()->where('user_id', $userId)->pluck('movie_id');

        return Movie::query()
            ->whereIn('id', $movieIds)
            ->get()
            ->mapWithKeys(fn (Movie $movie) => [($movie->media_type ?? 'movie').':'.$movie->tmdb_id => true])
            ->all();
    }
}
