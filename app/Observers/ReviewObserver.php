<?php

namespace App\Observers;

use App\Models\Review;

class ReviewObserver
{
    public function created(Review $review): void
    {
        $this->recalculate($review);
    }

    public function deleted(Review $review): void
    {
        $this->recalculate($review);
    }

    protected function recalculate(Review $review): void
    {
        $reviewable = $review->reviewable;

        if ($reviewable !== null) {
            $reviewable->forceFill([
                'reviews_count' => Review::where('reviewable_type', $review->reviewable_type)
                    ->where('reviewable_id', $review->reviewable_id)
                    ->count(),
            ])->saveQuietly();
        }
    }
}
