<?php

namespace App\ExerciseProfiles\Services;

final class ExerciseProfilePresetCatalog
{
    /**
     * @return list<array{
     *     name: string,
     *     slug: string,
     *     target_reps: int,
     *     floor_override: int|null,
     *     working_rest_seconds: int,
     *     warm_up_steps: list<array{mode?: string, percent?: int, reps: int}>
     * }>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'Strength',
                'slug' => 'preset-strength',
                'target_reps' => 6,
                'floor_override' => null,
                'working_rest_seconds' => 180,
                'warm_up_steps' => [
                    ['mode' => 'bar', 'reps' => 10],
                    ['mode' => 'percent', 'percent' => 50, 'reps' => 5],
                    ['mode' => 'percent', 'percent' => 75, 'reps' => 3],
                    ['mode' => 'percent', 'percent' => 90, 'reps' => 1],
                ],
            ],
            [
                'name' => 'Hypertrophy',
                'slug' => 'preset-hypertrophy',
                'target_reps' => 10,
                'floor_override' => null,
                'working_rest_seconds' => 90,
                'warm_up_steps' => [
                    ['percent' => 50, 'reps' => 10],
                    ['percent' => 80, 'reps' => 5],
                ],
            ],
            [
                'name' => 'Endurance',
                'slug' => 'preset-endurance',
                'target_reps' => 17,
                'floor_override' => null,
                'working_rest_seconds' => 60,
                'warm_up_steps' => [
                    ['percent' => 50, 'reps' => 10],
                    ['percent' => 75, 'reps' => 5],
                ],
            ],
        ];
    }
}
