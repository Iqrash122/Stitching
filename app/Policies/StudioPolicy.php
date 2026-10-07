<?php

namespace App\Policies;

use App\Models\User;

class StudioPolicy
{
    public function access(User $user): bool
    {
        return (bool) $user->is_owner;
    }
}
