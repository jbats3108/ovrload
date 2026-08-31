<?php

namespace App\Settings\Services;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Users\Models\User;
use App\Users\Services\PlateProfileService;
use App\Workouts\Models\Workout;

class UserDataExporter
{
    public function __construct(
        private readonly PlateProfileService $plateProfiles,
    ) {}

    /**
     * @return array{
     *     exported_at: string,
     *     profile: array<string, mixed>,
     *     plate_profile: array<string, mixed>,
     *     exercise_profiles: list<array<string, mixed>>,
     *     custom_exercises: list<array<string, mixed>>,
     *     routines: list<array<string, mixed>>,
     *     workouts: list<array<string, mixed>>
     * }
     */
    public function export(User $user): array
    {
        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => $this->profile($user),
            'plate_profile' => $this->plateProfiles->profilePayloadFor($user),
            'exercise_profiles' => $this->exerciseProfiles($user),
            'custom_exercises' => $this->customExercises($user),
            'routines' => $this->routines($user),
            'workouts' => $this->workouts($user),
        ];
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'weight_unit' => $user->weight_unit?->value,
            'achievement_floor_default' => $user->achievement_floor_default,
            'progression_target_default' => $user->progression_target_default,
            'progression_style_default' => $user->progression_style_default?->value,
            'progressive_mid_block_default' => $user->progressive_mid_block_default?->value,
            'deload_weight_factor_default' => $user->deload_weight_factor_default,
            'deload_reps_factor_default' => $user->deload_reps_factor_default,
            'deload_every_n_default' => $user->deload_every_n_default,
            'default_exercise_profile_id' => $user->default_exercise_profile_id,
            'warm_up_steps_default' => $user->warm_up_steps_default,
            'warm_up_defaults_scope' => $user->warm_up_defaults_scope?->value,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function exerciseProfiles(User $user): array
    {
        $profiles = $user->exerciseProfiles()
            ->where('kind', ExerciseProfileKind::Custom)
            ->orderBy('name')
            ->get();
        $payload = [];

        foreach ($profiles as $profile) {
            $payload[] = [
                'id' => $profile->id,
                'name' => $profile->name,
                'slug' => $profile->slug,
                'status' => $profile->status->value,
                'target_reps' => $profile->target_reps,
                'floor_override' => $profile->floor_override,
                'working_rest_seconds' => $profile->working_rest_seconds,
                'warm_up_steps' => $profile->warmUpStepList(),
                'recipe_fingerprint' => $profile->recipe_fingerprint,
            ];
        }

        return $payload;
    }

    /** @return list<array<string, mixed>> */
    private function customExercises(User $user): array
    {
        return array_values($user->customExercises()
            ->withTrashed()
            ->with(['primaryMuscleGroup:id,name,slug', 'secondaryMuscleGroup:id,name,slug'])
            ->orderBy('name')
            ->get()
            ->map(static fn (Exercise $exercise): array => [
                'id' => $exercise->id,
                'name' => $exercise->name,
                'slug' => $exercise->slug,
                'equipment' => $exercise->equipment?->value,
                'deleted_at' => $exercise->deleted_at?->toIso8601String(),
                'primary_muscle_group' => $exercise->primaryMuscleGroup === null
                    ? null
                    : [
                        'name' => $exercise->primaryMuscleGroup->name,
                        'slug' => $exercise->primaryMuscleGroup->slug,
                    ],
                'secondary_muscle_group' => $exercise->secondaryMuscleGroup === null
                    ? null
                    : [
                        'name' => $exercise->secondaryMuscleGroup->name,
                        'slug' => $exercise->secondaryMuscleGroup->slug,
                    ],
            ])
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function routines(User $user): array
    {
        return array_values($user->routines()
            ->withTrashed()
            ->with([
                'blocks.blockExercises.exercise:id,name,slug',
                'blocks.setGroups.warmUpSteps',
                'blocks.setGroups.dropsetSegments',
            ])
            ->orderBy('name')
            ->get()
            ->map(static fn (Routine $routine): array => $routine->toArray())
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function workouts(User $user): array
    {
        return array_values($user->workouts()
            ->withTrashed()
            ->with([
                'routine:id,name,slug',
                'blocks.blockExercises.exercise:id,name,slug',
                'blocks.setGroups.warmUpSteps',
                'blocks.setGroups.sets.segments',
                'bumpRecords',
            ])
            ->orderByDesc('started_at')
            ->get()
            ->map(static fn (Workout $workout): array => $workout->toArray())
            ->values()
            ->all());
    }
}
