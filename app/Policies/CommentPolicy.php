<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\ListModel;
use App\Models\Review;
use App\Models\User;

class CommentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->user_id === $user->id) {
            return true;
        }

        // El dueño del contenido puede borrar comentarios ajenos en él
        $commentable = $comment->commentable;

        return ($commentable instanceof Review || $commentable instanceof ListModel)
            && $commentable->user_id === $user->id;
    }
}
