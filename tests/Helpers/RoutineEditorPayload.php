<?php

declare(strict_types=1);

namespace Tests\Helpers;

final class RoutineEditorPayload
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function block(int $exerciseId, array $overrides = []): array
    {
        $exercise = [
            'exercise_id' => $exerciseId,
            'working_weight_kg' => $overrides['working_weight_kg'] ?? 60,
            'prescribed_reps' => $overrides['prescribed_reps'] ?? 6,
            'achievement_floor' => $overrides['achievement_floor'] ?? null,
            'floor_is_derived' => $overrides['floor_is_derived'] ?? null,
            'progression_target' => $overrides['progression_target'] ?? null,
            'exercise_profile_id' => $overrides['exercise_profile_id'] ?? null,
            'exercise_profile_fingerprint' => $overrides['exercise_profile_fingerprint'] ?? null,
            'deload_exercise_id' => $overrides['deload_exercise_id'] ?? null,
            'deload_working_weight_kg' => $overrides['deload_working_weight_kg'] ?? null,
        ];

        $exercises = $overrides['exercises'] ?? [$exercise];

        return [
            'is_superset' => $overrides['is_superset'] ?? false,
            'has_setup_after' => $overrides['has_setup_after'] ?? false,
            'has_setup_after_warm_up' => $overrides['has_setup_after_warm_up'] ?? false,
            'shared_profile_id' => $overrides['shared_profile_id'] ?? null,
            'shared_profile_fingerprint' => $overrides['shared_profile_fingerprint'] ?? null,
            'exercises' => $exercises,
            'working' => $overrides['working'] ?? ['set_count' => 3, 'rest_seconds' => 120],
            'warm_up' => $overrides['warm_up'] ?? ['set_count' => 0, 'rest_seconds' => 60, 'steps' => []],
        ];
    }
}
