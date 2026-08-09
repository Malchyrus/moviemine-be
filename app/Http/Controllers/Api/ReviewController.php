<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\MovieCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $reviews = Review::query()
            ->where('user_id', $user->id)
            ->with('movie')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Review $r) => $this->format($r));

        return response()->json(['reviews' => $reviews]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'poster_path' => ['nullable', 'string'],
            'backdrop_path' => ['nullable', 'string'],
            'release_date' => ['nullable', 'date'],
            'vote_average' => ['nullable', 'numeric', 'between:0,10'],
            'review' => ['required', 'string'],
            'contains_spoilers' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $movie = app(MovieCache::class)->getOrCreate($validated);

        $review = Review::query()->updateOrCreate(
            ['user_id' => $user->id, 'movie_id' => $movie->id],
            [
                'review' => $validated['review'],
                'contains_spoilers' => $validated['contains_spoilers'] ?? false,
            ],
        );

        return response()->json($this->format($review->load('movie')), 201);
    }

    public function destroy(Request $request, int $tmdbId): JsonResponse
    {
        $user = $request->user();
        $movie = app(MovieCache::class)->findByTmdb($tmdbId);

        if (! $movie) {
            return response()->json(['error' => 'not found'], 404);
        }

        $deleted = Review::query()
            ->where('user_id', $user->id)
            ->where('movie_id', $movie->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => 'not found'], 404);
        }

        return response()->json(['ok' => true]);
    }

    private function format(Review $review): array
    {
        return [
            'movie' => [
                'id' => $review->movie->tmdb_id,
                'title' => $review->movie->title,
                'poster_path' => $review->movie->poster_path,
            ],
            'review' => $review->review,
            'contains_spoilers' => (bool) $review->contains_spoilers,
            'created_at' => $review->created_at->toISOString(),
        ];
    }
}
