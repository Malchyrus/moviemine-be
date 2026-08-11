<?php

namespace App\Services;

use App\Models\Automation;
use App\Models\CustomList;
use App\Models\CustomListMovie;
use App\Models\Movie;
use App\Models\Watchlist;
use App\Models\User;

class LibraryService
{
    /**
     * Make sure the user has the five default lists.
     */
    public function ensureDefaultLists(User $user): void
    {
        $existing = $user->customLists()
            ->where('is_default', true)
            ->pluck('type')
            ->flip();

        $position = (int) $user->customLists()->max('position');

        foreach (CustomList::defaultTypes() as $type => $name) {
            if ($existing->has($type)) {
                continue;
            }

            $position++;

            CustomList::query()->create([
                'user_id' => $user->id,
                'name' => $name,
                'is_default' => true,
                'type' => $type,
                'position' => $position,
            ]);
        }
    }

    /**
     * Add a movie to a list. A movie can live in any number of custom lists,
     * but only one default list at a time.
     */
    public function addToList(User $user, Movie $movie, CustomList $list): CustomListMovie
    {
        $this->ensureDefaultLists($user);

        $this->ensureWatchlistRow($user, $movie);

        if ($list->is_default) {
            $this->removeFromDefaultLists($user, $movie, $list->id);
        }

        return CustomListMovie::query()->firstOrCreate(
            ['list_id' => $list->id, 'movie_id' => $movie->id],
            ['position' => $this->nextPosition($list)],
        );
    }

    public function removeFromList(User $user, Movie $movie, CustomList $list): void
    {
        CustomListMovie::query()
            ->where('list_id', $list->id)
            ->where('movie_id', $movie->id)
            ->delete();

        $remaining = CustomListMovie::query()
            ->where('movie_id', $movie->id)
            ->whereHas('list', fn ($q) => $q->where('user_id', $user->id))
            ->exists();

        if (! $remaining) {
            Watchlist::query()
                ->where('user_id', $user->id)
                ->where('movie_id', $movie->id)
                ->delete();
        }
    }

    /**
     * Move a movie between lists. Moving to a default list clears it from the
     * other default lists; custom list membership is additive.
     */
    public function moveToList(User $user, Movie $movie, CustomList $target, ?CustomList $from = null): void
    {
        if ($from && $from->id === $target->id) {
            return;
        }

        $this->addToList($user, $movie, $target);

        if ($from && ! $from->is_default) {
            $this->removeFromList($user, $movie, $from);
        }
    }

    /**
     * Mark a movie watched/unwatched, honouring the auto-move preference.
     */
    public function setWatched(User $user, Movie $movie, bool $watched): void
    {
        $entry = $this->ensureWatchlistRow($user, $movie);
        $entry->watched_at = $watched ? now() : null;
        $entry->save();

        $prefs = $user->preferencesOrDefault();

        if (! ($prefs['auto_move_watched'] ?? true)) {
            return;
        }

        $targetType = $watched ? 'completed' : 'planning';
        $target = $this->defaultList($user, $targetType);

        if ($target) {
            $this->moveToList($user, $movie, $target);
        }

        $this->evaluateAutomations($user, 'movie_watched', $movie, $entry->fresh());
    }

    /**
     * Set the status list a movie belongs to.
     */
    public function setStatus(User $user, Movie $movie, string $status): void
    {
        $target = $this->defaultList($user, $status);

        if (! $target) {
            return;
        }

        $this->moveToList($user, $movie, $target);
    }

    /**
     * Persist a rating and run rating automations.
     */
    public function setRating(User $user, Movie $movie, ?float $rating): void
    {
        $entry = $this->ensureWatchlistRow($user, $movie);
        $entry->rating = $rating;
        $entry->save();

        $this->evaluateAutomations($user, 'movie_rated', $movie, $entry->fresh());
    }

    /**
     * The default list of a given type, or null.
     */
    public function defaultList(User $user, string $type): ?CustomList
    {
        $this->ensureDefaultLists($user);

        return $user->customLists()
            ->where('is_default', true)
            ->where('type', $type)
            ->first();
    }

    /**
     * Which default list type contains the movie, if any.
     */
    public function statusOf(User $user, Movie $movie): ?string
    {
        $membership = CustomListMovie::query()
            ->where('movie_id', $movie->id)
            ->whereHas('list', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('is_default', true))
            ->with('list')
            ->first();

        return $membership?->list->type;
    }

    /**
     * Run matching enabled automations for an event.
     */
    public function evaluateAutomations(User $user, string $event, Movie $movie, ?Watchlist $entry = null): void
    {
        $status = $this->statusOf($user, $movie);

        $context = [
            'rating' => $entry?->rating,
            'watched' => (bool) ($entry?->watched_at),
            'status' => $status,
            'genres' => $movie->genres ?? [],
        ];

        $automations = $user->automations()
            ->where('event', $event)
            ->where('enabled', true)
            ->get();

        foreach ($automations as $automation) {
            if (! $this->matchesCondition($automation, $context)) {
                continue;
            }

            $action = $automation->action;
            $target = CustomList::query()
                ->where('id', $action['list_id'] ?? null)
                ->where('user_id', $user->id)
                ->first();

            if (! $target) {
                continue;
            }

            if (($action['type'] ?? 'add_to_list') === 'move_to_list') {
                $this->moveToList($user, $movie, $target);
            } else {
                $this->addToList($user, $movie, $target);
            }
        }
    }

    private function matchesCondition(Automation $automation, array $context): bool
    {
        $condition = $automation->condition;

        if (empty($condition) || ! isset($condition['field'], $condition['op'], $condition['value'])) {
            return true;
        }

        $actual = $context[$condition['field']] ?? null;
        $expected = $condition['value'];

        return match ($condition['op']) {
            '=' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            'contains' => is_array($actual) && in_array($expected, $actual, false),
            default => true,
        };
    }

    private function ensureWatchlistRow(User $user, Movie $movie): Watchlist
    {
        return Watchlist::query()->firstOrCreate([
            'user_id' => $user->id,
            'movie_id' => $movie->id,
        ]);
    }

    private function removeFromDefaultLists(User $user, Movie $movie, ?int $exceptListId = null): void
    {
        CustomListMovie::query()
            ->where('movie_id', $movie->id)
            ->where('list_id', '!=', $exceptListId)
            ->whereHas('list', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('is_default', true))
            ->delete();
    }

    private function nextPosition(CustomList $list): int
    {
        return ((int) CustomListMovie::query()
            ->where('list_id', $list->id)
            ->max('position')) + 1;
    }
}
