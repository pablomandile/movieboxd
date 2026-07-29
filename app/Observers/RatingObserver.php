<?php

namespace App\Observers;

use App\Models\Rating;

class RatingObserver
{
    public function created(Rating $rating): void
    {
        $this->recalculate($rating);
    }

    public function updated(Rating $rating): void
    {
        $this->recalculate($rating);
    }

    public function deleted(Rating $rating): void
    {
        $this->recalculate($rating);
    }

    protected function recalculate(Rating $rating): void
    {
        $rating->rateable?->recalculateRatingAggregates();
    }
}
