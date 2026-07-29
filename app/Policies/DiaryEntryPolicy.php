<?php

namespace App\Policies;

use App\Models\DiaryEntry;
use App\Models\User;

class DiaryEntryPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function update(User $user, DiaryEntry $entry): bool
    {
        return $entry->user_id === $user->id;
    }

    public function delete(User $user, DiaryEntry $entry): bool
    {
        return $entry->user_id === $user->id;
    }
}
