<?php

namespace App\Routines\Services;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Exercises\Models\Exercise;
use App\Routines\Data\Editor\SyncBlockExerciseData;
use App\Routines\Data\Editor\SyncDropsetData;
use App\Routines\Data\Editor\SyncRoutineBlockData;
use App\Routines\Data\Editor\SyncRoutineData;
use App\Routines\Data\Editor\SyncWarmUpData;
use App\Routines\Data\Editor\SyncWarmUpStepData;
use App\Routines\Exceptions\RoutineStaleException;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineDropsetSegment;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Shared\Data\WeightKgSegmentData;
use App\Shared\Enums\SetGroupType;
use App\Shared\Enums\WarmUpWeightMode;
use App\Shared\Support\WarmUpStepSupport;
use App\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RoutineEditorService
{
    public function __construct(
        private readonly ExerciseProfileService $exerciseProfiles,
    ) {}

    public function sync(Routine $routine, SyncRoutineData $data): Routine
    {
        return DB::transaction(function () use ($routine, $data): Routine {
            $locked = Routine::query()->whereKey($routine->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing('user');

            if ($data->expectedUpdatedAt !== null) {
                $expected = Carbon::parse($data->expectedUpdatedAt);
                if ($locked->updated_at === null || $locked->updated_at->getTimestamp() !== $expected->getTimestamp()) {
                    throw new RoutineStaleException;
                }
            }

            $defaultProfile = $this->selectableProfileFromId($locked->user, $data->defaultExerciseProfileId);

            $locked->update([
                'name' => $data->name,
                'deload_weight_factor' => $data->deloadWeightFactor ?? $locked->deload_weight_factor,
                'deload_reps_factor' => $data->deloadRepsFactor ?? $locked->deload_reps_factor,
                'deload_every_n' => $data->deloadEveryN ?? $locked->deload_every_n,
                'default_exercise_profile_id' => $defaultProfile === null
                    ? $locked->default_exercise_profile_id
                    : $defaultProfile->id,
            ]);

            $locked->blocks()->each(function (RoutineBlock $block): void {
                $block->delete();
            });

            $blocks = array_values(iterator_to_array($data->blocks ?? []));
            $lastIndex = count($blocks) - 1;

            foreach ($blocks as $index => $blockData) {
                /** @var SyncRoutineBlockData $blockData */
                $this->createBlock($locked, $index + 1, $blockData, $index === $lastIndex);
            }

            return $locked->fresh([
                'blocks.blockExercises.exercise',
                'blocks.setGroups.warmUpSteps',
                'blocks.setGroups.dropsetSegments',
            ]) ?? $locked;
        });
    }

    private function createBlock(Routine $routine, int $position, SyncRoutineBlockData $blockData, bool $isLastBlock = false): void
    {
        $exercises = $blockData->exercises->all();
        $sharedProfile = $this->profileFromId($routine->user, $blockData->sharedProfileId);
        $warmUp = $blockData->warmUp ?? new SyncWarmUpData;
        $steps = $warmUp->stepList();

        if ($blockData->isSuperset && count($exercises) !== 2) {
            throw new InvalidArgumentException('A superset must have exactly two exercises.');
        }

        if (! $blockData->isSuperset && count($exercises) !== 1) {
            throw new InvalidArgumentException('A non-superset must have exactly one exercise.');
        }

        $dropsets = $blockData->working->dropsetList();

        if ($blockData->isSuperset && $dropsets !== []) {
            throw new InvalidArgumentException('Dropsets are not supported on supersets.');
        }

        if (
            $sharedProfile !== null
            && $this->assignmentIsCurrent($blockData->sharedProfileFingerprint, $sharedProfile->recipe()->sharedFingerprint())
            && ! $this->sharedValuesMatchProfile($blockData, $sharedProfile, $steps)
        ) {
            throw new InvalidArgumentException('The shared profile values no longer match this block.');
        }
        $sharedFingerprint = $this->sharedProfileFingerprint($sharedProfile, $blockData->sharedProfileFingerprint);

        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'shared_exercise_profile_id' => $sharedProfile?->id,
            'shared_profile_fingerprint' => $sharedFingerprint,
            'position' => $position,
            'is_superset' => $blockData->isSuperset,
            'has_setup_after' => $isLastBlock ? false : $blockData->hasSetupAfter,
            'has_setup_after_warm_up' => $blockData->hasSetupAfterWarmUp,
        ]);

        foreach (array_values($exercises) as $index => $exerciseData) {
            /** @var SyncBlockExerciseData $exerciseData */
            Exercise::assertAvailableFor($routine->user, $exerciseData->exerciseId);
            $exerciseProfile = $this->profileFromId($routine->user, $exerciseData->exerciseProfileId);
            if (
                $exerciseProfile !== null
                && $this->assignmentIsCurrent(
                    $exerciseData->exerciseProfileFingerprint,
                    $this->exerciseProfileFingerprint($exerciseProfile, $blockData->isSuperset),
                )
                && ! $this->exerciseValuesMatchProfile($exerciseData, $exerciseProfile)
            ) {
                throw new InvalidArgumentException('The exercise profile values no longer match this exercise.');
            }

            if ($exerciseData->deloadExerciseId !== null) {
                Exercise::assertAvailableFor($routine->user, $exerciseData->deloadExerciseId);
            }
            $usesSupersetFingerprint = $blockData->isSuperset || $sharedProfile === null;
            $exerciseFingerprint = $this->exerciseProfileFingerprint(
                $exerciseProfile,
                $usesSupersetFingerprint,
            );
            $exerciseAssignmentIsCurrent = $exerciseProfile !== null
                && $this->assignmentIsCurrent($exerciseData->exerciseProfileFingerprint, $exerciseFingerprint);
            $storedExerciseFingerprint = $exerciseProfile === null
                ? null
                : ($exerciseData->exerciseProfileFingerprint ?? $exerciseFingerprint);

            RoutineBlockExercise::create([
                'routine_block_id' => $block->id,
                'exercise_profile_id' => $exerciseProfile?->id,
                'exercise_profile_fingerprint' => $storedExerciseFingerprint,
                'exercise_id' => $exerciseData->exerciseId,
                'position' => $index + 1,
                'working_weight_g' => $exerciseData->workingWeightGrams(),
                'deload_exercise_id' => $exerciseData->deloadExerciseId,
                'deload_working_weight_g' => $exerciseData->deloadWorkingWeightGrams(),
                'prescribed_reps' => $exerciseData->prescribedReps,
                'achievement_floor_override' => $this->achievementFloorForStorage($exerciseData),
                'floor_is_derived' => $this->floorDerivationForAssignment(
                    $exerciseProfile,
                    $exerciseData,
                    $exerciseAssignmentIsCurrent,
                ),
                'progression_target_override' => $exerciseData->progressionTarget,
            ]);
        }

        $workingGroup = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => $blockData->working->setCount,
            'rest_seconds' => $blockData->working->restSeconds,
        ]);

        $this->persistDropsets($workingGroup, $blockData->working->setCount, $dropsets);

        $warmUpGroup = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => max(count($steps), $warmUp->setCount),
            'rest_seconds' => $warmUp->restSeconds,
        ]);

        foreach ($steps as $stepIndex => $step) {
            RoutineWarmUpStep::create([
                'routine_set_group_id' => $warmUpGroup->id,
                'position' => $stepIndex + 1,
                'weight_mode' => $step->mode,
                'percent_of_working' => $step->mode === WarmUpWeightMode::Bar ? null : min(100, max(1, $step->percent ?? 1)),
                'reps' => min(100, max(1, $step->reps)),
                'has_setup_after' => $step->hasSetupAfter,
            ]);
        }
    }

    private function profileFromId(User $user, ?int $profileId): ?ExerciseProfile
    {
        if ($profileId === null) {
            return null;
        }

        $profile = ExerciseProfile::query()->findOrFail($profileId);
        $this->exerciseProfiles->assertAssignable($user, $profile);

        return $profile;
    }

    private function selectableProfileFromId(User $user, ?int $profileId): ?ExerciseProfile
    {
        if ($profileId === null) {
            return null;
        }

        $profile = $this->profileFromId($user, $profileId);
        $this->exerciseProfiles->assertSelectable($user, $profile);

        return $profile;
    }

    /**
     * @param  list<SyncWarmUpStepData>  $steps
     */
    private function sharedValuesMatchProfile(
        SyncRoutineBlockData $blockData,
        ExerciseProfile $profile,
        array $steps,
    ): bool {
        if ($blockData->working->restSeconds !== $profile->working_rest_seconds) {
            return false;
        }

        $warmUpSteps = array_values(array_map(
            static fn (SyncWarmUpStepData $step): array => WarmUpStepSupport::toStorage([
                'mode' => $step->mode,
                'percent' => $step->percent,
                'reps' => $step->reps,
            ]),
            $steps,
        ));

        return $warmUpSteps === $profile->warmUpStepList();
    }

    private function exerciseValuesMatchProfile(SyncBlockExerciseData $data, ExerciseProfile $profile): bool
    {
        if ($data->prescribedReps !== $profile->target_reps) {
            return false;
        }

        $profileFloorIsDerived = $profile->floor_override === null;

        if ($data->floorIsDerived !== $profileFloorIsDerived) {
            return false;
        }

        if ($data->floorIsDerived) {
            return true;
        }

        return $data->achievementFloor === $profile->floor_override;
    }

    private function floorDerivationForAssignment(
        ?ExerciseProfile $profile,
        SyncBlockExerciseData $data,
        bool $assignmentIsCurrent,
    ): ?bool {
        if ($profile === null || ! $assignmentIsCurrent) {
            return $data->floorIsDerived;
        }

        return $profile->floor_override === null;
    }

    private function achievementFloorForStorage(SyncBlockExerciseData $data): ?int
    {
        if ($data->floorIsDerived === true) {
            return null;
        }

        return $data->achievementFloor;
    }

    private function assignmentIsCurrent(?string $fingerprint, string $currentFingerprint): bool
    {
        return $fingerprint === null || $fingerprint === $currentFingerprint;
    }

    private function exerciseProfileFingerprint(?ExerciseProfile $profile, bool $isSuperset): ?string
    {
        if ($profile === null) {
            return null;
        }

        return $isSuperset
            ? $profile->recipe()->exerciseFingerprint()
            : $profile->recipe()->fingerprint();
    }

    private function sharedProfileFingerprint(?ExerciseProfile $profile, ?string $fingerprint): ?string
    {
        if ($profile === null) {
            return null;
        }

        return $fingerprint ?? $profile->recipe()->sharedFingerprint();
    }

    /**
     * @param  list<SyncDropsetData>  $dropsets
     */
    private function persistDropsets(RoutineSetGroup $workingGroup, int $setCount, array $dropsets): void
    {
        $seenIndexes = [];

        foreach ($dropsets as $dropset) {
            if ($dropset->setIndex < 0 || $dropset->setIndex >= $setCount) {
                throw new InvalidArgumentException(
                    "Dropset set index {$dropset->setIndex} is outside working set count {$setCount}."
                );
            }

            if (isset($seenIndexes[$dropset->setIndex])) {
                throw new InvalidArgumentException(
                    "Duplicate dropset entry for set index {$dropset->setIndex}."
                );
            }

            $seenIndexes[$dropset->setIndex] = true;

            $segments = array_values($dropset->segments->all());

            if (count($segments) < 2) {
                throw new InvalidArgumentException('A dropset requires at least two segments.');
            }

            foreach ($segments as $segmentIndex => $segment) {
                /** @var WeightKgSegmentData $segment */
                RoutineDropsetSegment::create([
                    'routine_set_group_id' => $workingGroup->id,
                    'set_index' => $dropset->setIndex,
                    'position' => $segmentIndex + 1,
                    'weight_g' => $segment->weightGrams(),
                ]);
            }
        }
    }
}
