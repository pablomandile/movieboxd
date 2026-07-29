<?php

namespace App\Services;

use App\Http\Controllers\ReviewController;
use App\Models\DiaryEntry;
use App\Models\Episode;
use App\Models\Review;
use App\Models\Season;
use App\Models\Title;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Feed derivado por query (regla 7): solo entradas de diario y reviews
 * standalone de los seguidos — nunca watched/ratings sueltos. Una review
 * atada a un log no se duplica (el diary entry ya la representa).
 */
class ActivityFeedService
{
    public function for(User $user, int $limit = 20): Collection
    {
        $followedIds = $user->following()->pluck('users.id');

        if ($followedIds->isEmpty()) {
            return collect();
        }

        $diary = DB::table('diary_entries')
            ->selectRaw("'diary' as kind, id, created_at")
            ->whereIn('user_id', $followedIds);

        $reviews = DB::table('reviews')
            ->selectRaw("'review' as kind, id, created_at")
            ->whereIn('user_id', $followedIds)
            ->whereNull('diary_entry_id');

        $rows = $diary->unionAll($reviews)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $diaryEntries = DiaryEntry::with([
            'user',
            'review',
            'loggable' => fn ($morphTo) => $morphTo->morphWith([
                Season::class => ['title'],
                Episode::class => ['title'],
            ]),
        ])->findMany($rows->where('kind', 'diary')->pluck('id'))->keyBy('id');

        $standaloneReviews = Review::with(['user', 'reviewable', 'diaryEntry'])
            ->findMany($rows->where('kind', 'review')->pluck('id'))
            ->keyBy('id');

        return $rows
            ->map(function ($row) use ($diaryEntries, $standaloneReviews) {
                return $row->kind === 'diary'
                    ? $this->diaryItem($diaryEntries->get($row->id))
                    : $this->reviewItem($standaloneReviews->get($row->id));
            })
            ->filter()
            ->values();
    }

    protected function diaryItem(?DiaryEntry $entry): ?array
    {
        if ($entry === null || $entry->loggable === null) {
            return null;
        }

        return [
            'kind' => 'diary',
            'id' => "diary-{$entry->id}",
            'when' => $entry->created_at->toIso8601String(),
            'watchedOn' => $entry->watched_on->toDateString(),
            'user' => $entry->user->only('name', 'username', 'avatar_path'),
            'subject' => $this->subject($entry->loggable),
            'rating' => $entry->rating,
            'isRewatch' => $entry->is_rewatch,
            'review' => $entry->review ? [
                'excerpt' => str($entry->review->body)->limit(280)->toString(),
                'containsSpoilers' => $entry->review->contains_spoilers,
                'url' => route('reviews.show', $entry->review),
            ] : null,
        ];
    }

    protected function reviewItem(?Review $review): ?array
    {
        if ($review === null || $review->reviewable === null) {
            return null;
        }

        return [
            'kind' => 'review',
            'id' => "review-{$review->id}",
            'when' => $review->created_at->toIso8601String(),
            'watchedOn' => null,
            'user' => $review->user->only('name', 'username', 'avatar_path'),
            'subject' => $this->subject($review->reviewable),
            'rating' => null,
            'isRewatch' => false,
            'review' => [
                'excerpt' => str($review->body)->limit(280)->toString(),
                'containsSpoilers' => $review->contains_spoilers,
                'url' => route('reviews.show', $review),
            ],
        ];
    }

    protected function subject($model): ?array
    {
        return match (true) {
            $model instanceof Title, $model instanceof Season, $model instanceof Episode => ReviewController::subjectProps($model),
            default => null,
        };
    }
}
