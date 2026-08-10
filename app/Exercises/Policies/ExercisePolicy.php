<?php

namespace App\Exercises\Policies;

use App\Exercises\Models\Exercise;
use App\Users\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExercisePolicy
{
    use HandlesAuthorization;

    public function delete(User $user, Exercise $exercise): bool
    {
        if ($exercise->isShared()) {
            return $user->isAdmin();
        }

        return $exercise->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function createCustom(User $user): bool
    {
        return true;
    }
}
