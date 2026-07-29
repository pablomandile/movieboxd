<?php

namespace App\Policies;

use App\Models\ListModel;
use App\Models\User;

class ListModelPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function view(?User $user, ListModel $list): bool
    {
        return $list->is_public || ($user !== null && $list->user_id === $user->id);
    }

    public function update(User $user, ListModel $list): bool
    {
        return $list->user_id === $user->id;
    }

    public function delete(User $user, ListModel $list): bool
    {
        return $list->user_id === $user->id;
    }
}
