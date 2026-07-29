<?php

namespace App\Observers;

use App\Models\Comment;

class CommentObserver
{
    public function created(Comment $comment): void
    {
        $this->recalculate($comment);
    }

    public function deleted(Comment $comment): void
    {
        $this->recalculate($comment);
    }

    protected function recalculate(Comment $comment): void
    {
        $commentable = $comment->commentable;

        if ($commentable !== null && array_key_exists('comments_count', $commentable->getAttributes())) {
            $commentable->forceFill([
                'comments_count' => Comment::where('commentable_type', $comment->commentable_type)
                    ->where('commentable_id', $comment->commentable_id)
                    ->count(),
            ])->saveQuietly();
        }
    }
}
