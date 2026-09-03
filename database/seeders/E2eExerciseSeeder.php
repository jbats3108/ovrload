<?php

namespace Database\Seeders;

use App\Exercises\Enums\ExerciseEquipment;
use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Minimal shared exercises for Playwright e2e (RoutineSeeder demo routines).
 */
final class E2eExerciseSeeder extends Seeder
{
    /** @var list<array{name: string, equipment: ExerciseEquipment}> */
    private const array EXERCISES = [
        ['name' => 'Barbell Bench Press - Medium Grip', 'equipment' => ExerciseEquipment::Barbell],
        ['name' => 'Bent Over Barbell Row', 'equipment' => ExerciseEquipment::Barbell],
        ['name' => 'Barbell Squat', 'equipment' => ExerciseEquipment::Barbell],
        ['name' => 'Dumbbell Bench Press', 'equipment' => ExerciseEquipment::Dumbbell],
        ['name' => 'Arnold Dumbbell Press', 'equipment' => ExerciseEquipment::Dumbbell],
        ['name' => 'Alternate Hammer Curl', 'equipment' => ExerciseEquipment::Dumbbell],
        ['name' => 'Bench Dips', 'equipment' => ExerciseEquipment::BodyOnly],
        ['name' => 'Close-Grip Barbell Bench Press', 'equipment' => ExerciseEquipment::Barbell],
        ['name' => 'Barbell Curl', 'equipment' => ExerciseEquipment::Barbell],
        ['name' => 'Bent Over Two-Dumbbell Row', 'equipment' => ExerciseEquipment::Dumbbell],
        ['name' => 'Barbell Shoulder Press', 'equipment' => ExerciseEquipment::Barbell],
    ];

    public function run(): void
    {
        $muscleGroup = MuscleGroup::query()->firstOrCreate(
            ['slug' => 'chest'],
            ['name' => 'Chest'],
        );

        foreach (self::EXERCISES as $exercise) {
            Exercise::query()->updateOrCreate(
                ['slug' => Str::slug($exercise['name'])],
                [
                    'user_id' => null,
                    'name' => $exercise['name'],
                    'primary_muscle_group_id' => $muscleGroup->id,
                    'secondary_muscle_group_id' => null,
                    'equipment' => $exercise['equipment'],
                ],
            );
        }
    }
}
