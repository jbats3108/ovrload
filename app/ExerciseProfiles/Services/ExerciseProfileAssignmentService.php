<?php

namespace App\ExerciseProfiles\Services;

use App\ExerciseProfiles\Exceptions\ExerciseProfileNotEditableException;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Support\ExerciseProfileAssignment;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineSetGroup;
use App\Shared\Enums\SetGroupType;
use App\Shared\Support\WarmUpStepSupport;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class ExerciseProfileAssignmentService
{
    public function syncProfile(User $user, ExerciseProfile $profile): int
    {
        $this->assertEditableCustom($user, $profile);

        return DB::transaction(function () use ($user, $profile): int {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedProfile = ExerciseProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $recipe = $lockedProfile->recipe();
            $updated = 0;

            $routines = $user->routines()
                ->with([
                    'blocks.blockExercises',
                    'blocks.setGroups.warmUpSteps',
                ])
                ->get();

            foreach ($routines as $routine) {
                foreach ($routine->blocks as $block) {
                    $sharedAssigned = $block->shared_exercise_profile_id === $lockedProfile->id;
                    if ($sharedAssigned) {
                        $this->materializeSharedRecipe($block, $recipe);
                        $block->forceFill([
                            'shared_profile_fingerprint' => $recipe->sharedFingerprint(),
                        ])->save();
                        $updated++;
                    }

                    foreach ($block->blockExercises as $exercise) {
                        if ($exercise->exercise_profile_id !== $lockedProfile->id) {
                            continue;
                        }

                        $exercise->forceFill([
                            'prescribed_reps' => $recipe->targetReps,
                            'achievement_floor_override' => $recipe->floorOverride,
                            'floor_is_derived' => $recipe->floorOverride === null,
                            'exercise_profile_fingerprint' => ExerciseProfileAssignment::expectedExerciseFingerprint(
                                $recipe,
                                $block->is_superset,
                                $sharedAssigned,
                            ),
                        ])->save();
                        $updated++;
                    }
                }
            }

            return $updated;
        });
    }

    public function liveRoutineCountFor(User $user, ExerciseProfile $profile): int
    {
        return count($this->assignedRoutinesByProfileId($user, new Collection([$profile]))[$profile->id] ?? []);
    }

    /**
     * @param  Collection<int, ExerciseProfile>  $profiles
     * @return array<int, list<array{name: string, slug: string}>>
     */
    public function assignedRoutinesByProfileId(User $user, Collection $profiles): array
    {
        /** @var array<int, list<array{name: string, slug: string}>> $map */
        $map = [];
        foreach ($profiles as $profile) {
            $map[$profile->id] = [];
        }

        if ($map === []) {
            return $map;
        }

        $routines = $user->routines()
            ->with(['blocks.blockExercises'])
            ->orderBy('name')
            ->get();

        foreach ($routines as $routine) {
            $summary = [
                'name' => $routine->name,
                'slug' => (string) $routine->slug,
            ];

            foreach ($this->profileIdsReferencedBy($routine) as $profileId) {
                if (! array_key_exists($profileId, $map)) {
                    continue;
                }

                $map[$profileId][] = $summary;
            }
        }

        return $map;
    }

    /**
     * Profile IDs shown by the routine profile picker or an exercise profile picker.
     *
     * @return list<int>
     */
    public function profileIdsReferencedBy(Routine $routine): array
    {
        return $this->exerciseProfileIdsReferencedBy($routine);
    }

    /**
     * @param  Collection<int, ExerciseProfile>  $profiles
     * @return array<int, int>
     */
    public function staleAssignmentCountsForUser(User $user, Collection $profiles): array
    {
        $customProfiles = $profiles
            ->filter(fn (ExerciseProfile $profile): bool => $profile->isCustom())
            ->keyBy('id');

        /** @var array<int, int> $counts */
        $counts = $customProfiles
            ->map(fn (): int => 0)
            ->all();

        if ($counts === []) {
            return $counts;
        }

        /** @var array<int, ExerciseProfileRecipe> $recipes */
        $recipes = $customProfiles
            ->map(fn (ExerciseProfile $profile): ExerciseProfileRecipe => $profile->recipe())
            ->all();

        $routines = $user->routines()
            ->with(['blocks.blockExercises'])
            ->get();

        foreach ($routines as $routine) {
            foreach ($routine->blocks as $block) {
                $sharedProfileId = $block->shared_exercise_profile_id;
                $sharedAssignedToTrackedProfile = $sharedProfileId !== null && isset($counts[$sharedProfileId]);

                $exerciseUsesSharedProfile = $sharedAssignedToTrackedProfile && $block->blockExercises->contains(
                    fn (RoutineBlockExercise $exercise): bool => $exercise->exercise_profile_id === $sharedProfileId,
                );

                if ($exerciseUsesSharedProfile) {
                    $sharedRecipe = $recipes[$sharedProfileId];
                    if ($block->shared_profile_fingerprint !== $sharedRecipe->sharedFingerprint()) {
                        $counts[$sharedProfileId]++;
                    }
                }

                foreach ($block->blockExercises as $exercise) {
                    $exerciseProfileId = $exercise->exercise_profile_id;
                    if ($exerciseProfileId === null || ! isset($counts[$exerciseProfileId])) {
                        continue;
                    }

                    $exerciseRecipe = $recipes[$exerciseProfileId];
                    $expectedFingerprint = ExerciseProfileAssignment::expectedExerciseFingerprint(
                        $exerciseRecipe,
                        $block->is_superset,
                        $sharedAssignedToTrackedProfile,
                    );

                    if ($exercise->exercise_profile_fingerprint !== $expectedFingerprint) {
                        $counts[$exerciseProfileId]++;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * Exercise-level and routine-default profile references (excludes block shared profile).
     *
     * @return list<int>
     */
    private function exerciseProfileIdsReferencedBy(Routine $routine): array
    {
        $ids = [];

        if ($routine->default_exercise_profile_id !== null) {
            $ids[] = $routine->default_exercise_profile_id;
        }

        foreach ($routine->blocks as $block) {
            foreach ($block->blockExercises as $exercise) {
                if ($exercise->exercise_profile_id !== null) {
                    $ids[] = $exercise->exercise_profile_id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function materializeSharedRecipe(RoutineBlock $block, ExerciseProfileRecipe $recipe): void
    {
        $working = $block->setGroups->firstWhere('type', SetGroupType::Working);
        if ($working instanceof RoutineSetGroup) {
            $working->forceFill(['rest_seconds' => $recipe->workingRestSeconds])->save();
        }

        $warmUp = $block->setGroups->firstWhere('type', SetGroupType::WarmUp);
        if (! $warmUp instanceof RoutineSetGroup) {
            return;
        }

        $existingSetupFlags = $warmUp->warmUpSteps
            ->map(static fn ($step): bool => (bool) $step->has_setup_after)
            ->values()
            ->all();

        $warmUp->warmUpSteps()->delete();
        foreach ($recipe->warmUpSteps as $index => $step) {
            $normalized = WarmUpStepSupport::normalize($step);
            if ($normalized === null) {
                continue;
            }

            $warmUp->warmUpSteps()->create([
                'position' => $index + 1,
                'weight_mode' => $normalized['mode'],
                'percent_of_working' => $normalized['percent'],
                'weight_g' => $normalized['weight_g'],
                'reps' => $normalized['reps'],
                'has_setup_after' => $existingSetupFlags[$index] ?? false,
            ]);
        }
        $warmUp->forceFill(['set_count' => count($recipe->warmUpSteps)])->save();
        if ($recipe->warmUpSteps === []) {
            $block->forceFill(['has_setup_after_warm_up' => false])->save();
        }
    }

    private function assertEditableCustom(User $user, ExerciseProfile $profile): void
    {
        if (! $profile->isCustom() || $profile->user_id !== $user->id) {
            throw new ExerciseProfileNotEditableException('That profile is not yours to change.');
        }

        if ($profile->isArchived()) {
            throw new ExerciseProfileNotEditableException('Restore the profile before editing it.');
        }
    }
}
