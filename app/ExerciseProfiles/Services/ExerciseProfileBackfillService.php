<?php

namespace App\ExerciseProfiles\Services;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineSetGroup;
use App\Shared\Enums\WarmUpWeightMode;
use App\Shared\Support\WarmUpStepSupport;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ExerciseProfileBackfillService
{
    /** @var array<string, ExerciseProfile> */
    private array $presetCache = [];

    public function run(): void
    {
        $this->ensurePresets();

        User::query()->chunkById(100, function (Collection $users): void {
            foreach ($users as $user) {
                $this->backfillUser($user);
            }
        });
    }

    public function backfillUser(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $defaultProfile = $lockedUser->defaultExerciseProfile
                ?? $this->profileForLegacyDefaults($lockedUser);

            if ($lockedUser->default_exercise_profile_id === null) {
                $lockedUser->forceFill([
                    'default_exercise_profile_id' => $defaultProfile->id,
                ])->save();
            }

            $routines = $lockedUser->routines()
                ->withTrashed()
                ->with([
                    'blocks.blockExercises',
                    'blocks.blockExercises.exerciseProfile',
                    'blocks.setGroups.warmUpSteps',
                ])
                ->get();

            foreach ($routines as $routine) {
                if ($routine->default_exercise_profile_id === null) {
                    $routine->forceFill([
                        'default_exercise_profile_id' => $defaultProfile->id,
                    ])->save();
                }

                foreach ($routine->blocks as $block) {
                    $this->backfillBlock($lockedUser, $block);
                }
            }
        });
    }

    private function ensurePresets(): void
    {
        foreach (ExerciseProfilePresetCatalog::definitions() as $definition) {
            $recipe = new ExerciseProfileRecipe(
                targetReps: $definition['target_reps'],
                floorOverride: $definition['floor_override'],
                workingRestSeconds: $definition['working_rest_seconds'],
                warmUpSteps: $definition['warm_up_steps'],
            );

            $profile = ExerciseProfile::query()->firstOrCreate(
                [
                    'slug_scope' => 'system',
                    'slug' => $definition['slug'],
                ],
                [
                    'user_id' => null,
                    'created_by_user_id' => null,
                    'kind' => ExerciseProfileKind::Preset,
                    'status' => ExerciseProfileStatus::Published,
                    'name' => $definition['name'],
                    'target_reps' => $recipe->targetReps,
                    'floor_override' => $recipe->floorOverride,
                    'working_rest_seconds' => $recipe->workingRestSeconds,
                    'warm_up_steps' => $recipe->warmUpSteps,
                    'recipe_fingerprint' => $recipe->fingerprint(),
                    'published_at' => now(),
                ],
            );

            $this->presetCache[$profile->recipe()->fingerprint()] = $profile;
        }
    }

    private function profileForLegacyDefaults(User $user): ExerciseProfile
    {
        $warmUpSteps = $user->warm_up_steps_default ?? User::fallbackWarmUpSteps();

        $recipe = new ExerciseProfileRecipe(
            targetReps: $user->resolvedDefaultTargetReps(),
            floorOverride: $user->achievement_floor_default ?? 1,
            workingRestSeconds: 120,
            warmUpSteps: WarmUpStepSupport::normalizeList($warmUpSteps),
        );

        return $this->profileForRecipe($user, $recipe);
    }

    private function backfillBlock(User $user, RoutineBlock $block): void
    {
        $working = $block->setGroups->firstWhere('type', 'working');
        $warmUp = $block->setGroups->firstWhere('type', 'warm_up');

        if (! $working instanceof RoutineSetGroup || $block->blockExercises->isEmpty()) {
            return;
        }

        $warmUpSteps = $warmUp instanceof RoutineSetGroup
            ? array_values($warmUp->warmUpSteps
                ->map(static fn ($step): array => WarmUpStepSupport::toStorage(
                    WarmUpStepSupport::normalize([
                        'mode' => ($step->weight_mode ?? WarmUpWeightMode::Percent)->value,
                        'percent' => $step->percent_of_working,
                        'weight_g' => $step->weight_g,
                        'reps' => (int) ($step->reps ?? 5),
                    ]) ?? ['mode' => 'percent', 'percent' => 50, 'reps' => 5],
                ))
                ->all())
            : [];

        $sharedRecipe = new ExerciseProfileRecipe(
            targetReps: $block->blockExercises->first()->prescribed_reps,
            floorOverride: $this->legacyFloor($user, $block->blockExercises->first()),
            workingRestSeconds: (int) $working->rest_seconds,
            warmUpSteps: $warmUpSteps,
        );

        $profiles = [];
        foreach ($block->blockExercises as $blockExercise) {
            $recipe = new ExerciseProfileRecipe(
                targetReps: (int) $blockExercise->prescribed_reps,
                floorOverride: $this->legacyFloor($user, $blockExercise),
                workingRestSeconds: $sharedRecipe->workingRestSeconds,
                warmUpSteps: $sharedRecipe->warmUpSteps,
            );

            $existingProfile = $blockExercise->exerciseProfile;
            $profiles[$blockExercise->id] = $existingProfile
                ?? $this->profileForRecipe(
                    $user,
                    $recipe,
                    preferPresetByExerciseValues: $block->is_superset,
                );

            $blockExercise->forceFill([
                'exercise_profile_id' => $profiles[$blockExercise->id]->id,
                'exercise_profile_fingerprint' => $existingProfile?->id === $profiles[$blockExercise->id]->id
                    && $blockExercise->exercise_profile_fingerprint !== null
                    ? $blockExercise->exercise_profile_fingerprint
                    : ($block->is_superset
                        ? $recipe->exerciseFingerprint()
                        : $recipe->fingerprint()),
                'floor_is_derived' => $profiles[$blockExercise->id]->floor_override === null,
            ])->save();
        }

        $sharedProfile = $profiles[$block->blockExercises->first()->id];
        if ($sharedProfile->recipe()->sharedFingerprint() === $sharedRecipe->sharedFingerprint()) {
            $block->forceFill([
                'shared_exercise_profile_id' => $sharedProfile->id,
                'shared_profile_fingerprint' => $sharedRecipe->sharedFingerprint(),
            ])->save();
        }
    }

    private function legacyFloor(User $user, RoutineBlockExercise $exercise): int
    {
        return $exercise->achievement_floor_override
            ?? $user->achievement_floor_default
            ?? 1;
    }

    private function profileForRecipe(
        User $user,
        ExerciseProfileRecipe $recipe,
        bool $preferPresetByExerciseValues = false,
    ): ExerciseProfile {
        if ($preferPresetByExerciseValues) {
            foreach ($this->presetCache as $preset) {
                if ($preset->recipe()->exerciseFingerprint() === $recipe->exerciseFingerprint()) {
                    return $preset;
                }
            }
        }

        $fingerprint = $recipe->fingerprint();
        $existing = $user->exerciseProfiles()
            ->where('recipe_fingerprint', $fingerprint)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $preset = $this->presetCache[$fingerprint] ?? null;
        if ($preset !== null) {
            return $preset;
        }

        return $this->createCustomProfile($user, $recipe);
    }

    private function createCustomProfile(User $user, ExerciseProfileRecipe $recipe): ExerciseProfile
    {
        $index = 1;
        do {
            $name = "My Custom {$index}";
            $index++;
        } while ($user->exerciseProfiles()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists());

        $slug = 'my-custom-'.($index - 1);
        while ($user->exerciseProfiles()->where('slug', $slug)->exists()) {
            $slug = 'my-custom-'.$index;
            $index++;
        }

        return ExerciseProfile::create([
            'user_id' => $user->id,
            'created_by_user_id' => null,
            'kind' => ExerciseProfileKind::Custom,
            'status' => ExerciseProfileStatus::Published,
            'name' => $name,
            'slug' => $slug,
            'slug_scope' => 'user-'.$user->id,
            'target_reps' => $recipe->targetReps,
            'floor_override' => $recipe->floorOverride,
            'working_rest_seconds' => $recipe->workingRestSeconds,
            'warm_up_steps' => $recipe->canonicalPayload()['warm_up_steps'],
            'recipe_fingerprint' => $recipe->fingerprint(),
            'published_at' => now(),
        ]);
    }
}
