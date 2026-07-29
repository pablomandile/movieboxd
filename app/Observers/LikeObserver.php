<?php

namespace App\Observers;

use App\Models\Like;

class LikeObserver
{
    public function created(Like $like): void
    {
        $this->recalculate($like);
    }

    public function deleted(Like $like): void
    {
        $this->recalculate($like);
    }

    protected function recalculate(Like $like): void
    {
        $likeable = $like->likeable;

        // Todo likeable con columna likes_count la mantiene cacheada
        if ($likeable !== null && array_key_exists('likes_count', $likeable->getAttributes())) {
            $likeable->forceFill([
                'likes_count' => $likeable->morphMany(Like::class, 'likeable')->count(),
            ])->saveQuietly();
        }
    }
}
