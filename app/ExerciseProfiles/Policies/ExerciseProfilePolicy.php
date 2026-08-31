<?php

namespace App\ExerciseProfiles\Policies;

use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Users\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExerciseProfilePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ExerciseProfile $exerciseProfile): bool
    {
        return $exerciseProfile->isPreset()
            ? $exerciseProfile->status === ExerciseProfileStatus::Published
            : $exerciseProfile->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ExerciseProfile $exerciseProfile): bool
    {
        if ($exerciseProfile->isPreset()) {
            return $user->isAdmin()
                && $exerciseProfile->status === ExerciseProfileStatus::Draft;
        }

        return $exerciseProfile->isCustom()
            && $exerciseProfile->user_id === $user->id
            && $exerciseProfile->status === ExerciseProfileStatus::Published;
    }

    public function delete(User $user, ExerciseProfile $exerciseProfile): bool
    {
        if ($exerciseProfile->isPreset()) {
            return $user->isAdmin()
                && $exerciseProfile->status === ExerciseProfileStatus::Draft;
        }

        return $exerciseProfile->isCustom() && $exerciseProfile->user_id === $user->id;
    }

    public function restore(User $user, ExerciseProfile $exerciseProfile): bool
    {
        return $exerciseProfile->isCustom()
            && $exerciseProfile->user_id === $user->id
            && $exerciseProfile->status === ExerciseProfileStatus::Archived;
    }

    public function publish(User $user, ExerciseProfile $exerciseProfile): bool
    {
        return $user->isAdmin()
            && $exerciseProfile->isPreset()
            && $exerciseProfile->status === ExerciseProfileStatus::Draft;
    }
}
