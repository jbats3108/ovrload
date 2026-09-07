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
            'prescribed_reps' => array_key_exists('prescribed_reps', $overrides) ? $overrides['prescribed_reps'] : 6,
            'prescription_mode' => $overrides['prescription_mode'] ?? 'reps',
            'prescribed_duration_seconds' => $overrides['prescribed_duration_seconds'] ?? null,
            'achievement_floor' => $overrides['achievement_floor'] ?? null,
            'floor_is_derived' => $overrides['floor_is_derived'] ?? null,
            'progression_target' => $overrides['progression_target'] ?? null,
            'exercise_profile_id' => $overrides['exercise_profile_id'] ?? null,
            'exercise_profile_fingerprint' => $overrides['exercise_profile_fingerprint'] ?? null,
            'deload_exercise_id' => $overrides['deload_exercise_id'] ?? null,
            'deload_working_weight_kg' => $overrides['deload_working_weight_kg'] ?? null,
        ];

        $exercises = $overrides['exercises'] ?? [$exercise];

        $block = [
            'is_superset' => $overrides['is_superset'] ?? false,
            'has_setup_after' => $overrides['has_setup_after'] ?? false,
            'has_setup_after_warm_up' => $overrides['has_setup_after_warm_up'] ?? false,
            'shared_profile_id' => $overrides['shared_profile_id'] ?? null,
            'shared_profile_fingerprint' => $overrides['shared_profile_fingerprint'] ?? null,
            'exercises' => $exercises,
            'working' => $overrides['working'] ?? ['set_count' => 3, 'rest_seconds' => 120],
            'warm_up' => $overrides['warm_up'] ?? ['set_count' => 0, 'rest_seconds' => 60, 'steps' => []],
        ];

        if (array_key_exists('type', $overrides)) {
            $block['type'] = $overrides['type'];
        }

        if (array_key_exists('stage_rest_seconds', $overrides)) {
            $block['stage_rest_seconds'] = $overrides['stage_rest_seconds'];
        }

        return $block;
    }

    /**
     * @param  list<int|array<string, mixed>>  $exercisesList
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function circuitBlock(array $exercisesList, array $overrides = []): array
    {
        $exercises = [];
        foreach ($exercisesList as $item) {
            if (is_int($item)) {
                $exercises[] = [
                    'exercise_id' => $item,
                    'working_weight_kg' => 0,
                    'prescription_mode' => 'reps',
                    'prescribed_reps' => 10,
                ];
            } else {
                $hasExplicitReps = array_key_exists('prescribed_reps', $item);
                $hasExplicitDuration = array_key_exists('prescribed_duration_seconds', $item);
                $prescriptionMode = $item['prescription_mode'] ?? ($hasExplicitDuration ? 'duration' : 'reps');

                $exercises[] = array_merge([
                    'working_weight_kg' => 0,
                    'prescription_mode' => $prescriptionMode,
                    'prescribed_reps' => $hasExplicitReps ? $item['prescribed_reps'] : ($prescriptionMode === 'duration' ? null : 10),
                    'prescribed_duration_seconds' => $hasExplicitDuration ? $item['prescribed_duration_seconds'] : null,
                ], $item);
            }
        }

        return array_merge([
            'type' => 'circuit',
            'is_superset' => false,
            'stage_rest_seconds' => $overrides['stage_rest_seconds'] ?? 15,
            'has_setup_after' => false,
            'has_setup_after_warm_up' => false,
            'shared_profile_id' => null,
            'shared_profile_fingerprint' => null,
            'exercises' => $exercises,
            'working' => $overrides['working'] ?? ['set_count' => 3, 'rest_seconds' => 60],
            'warm_up' => $overrides['warm_up'] ?? ['set_count' => 0, 'rest_seconds' => 60, 'steps' => []],
        ], $overrides, ['exercises' => $exercises]);
    }
}
