<?php

declare(strict_types=1);

namespace App\Exercises\Services;

use App\Exercises\Models\Exercise;
use App\Users\Models\User;
use Illuminate\Support\Str;

final class CustomExerciseSlugGenerator
{
    public static function forUser(User $user, string $name, ?int $ignoreExerciseId = null): string
    {
        $base = Str::slug($name) ?: 'exercise';
        $slug = $base;
        $suffix = 2;

        while (self::existsForUser($user, $slug, $ignoreExerciseId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function existsForUser(User $user, string $slug, ?int $ignoreExerciseId): bool
    {
        $query = Exercise::withTrashed()
            ->where('user_id', $user->id)
            ->where('slug', $slug);

        if ($ignoreExerciseId !== null) {
            $query->whereKeyNot($ignoreExerciseId);
        }

        return $query->exists();
    }
}
