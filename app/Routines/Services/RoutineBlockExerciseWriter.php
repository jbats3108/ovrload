<?php

namespace App\Routines\Services;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Support\ExerciseProfileAssignment;
use App\Exercises\Models\Exercise;
use App\Routines\Data\Editor\SyncBlockExerciseData;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Support\RoutineBlockShape;
use App\Shared\Enums\PrescriptionMode;
use App\Users\Models\User;
use InvalidArgumentException;

final readonly class RoutineBlockExerciseWriter
{
    public function __construct(
        private RoutineBlockProfileGuard $profiles,
    ) {}

    public function create(
        User $user,
        RoutineBlock $block,
        SyncBlockExerciseData $exerciseData,
        int $position,
        bool $isSuperset,
        bool $isCircuit,
        ?ExerciseProfile $sharedProfile,
    ): void {
        Exercise::assertAvailableFor($user, $exerciseData->exerciseId);

        if ($isCircuit) {
            RoutineBlockShape::assertCircuitExercise($exerciseData);
        }

        if ($isSuperset && $exerciseData->prescriptionMode === PrescriptionMode::Duration) {
            throw new InvalidArgumentException('Timed exercises are not supported on supersets.');
        }

        RoutineBlockShape::assertTimedExercise($exerciseData);

        if ($exerciseData->prescriptionMode === PrescriptionMode::Duration) {
            if ($exerciseData->prescribedDurationSeconds === null || $exerciseData->prescribedDurationSeconds < 1) {
                throw new InvalidArgumentException('Timed exercises require a duration of at least 1 second.');
            }
        } elseif ($exerciseData->prescribedReps === null || $exerciseData->prescribedReps < 1) {
            throw new InvalidArgumentException('Rep-based exercises require prescribed reps of at least 1.');
        }

        $exerciseProfile = $isCircuit ? null : $this->profiles->profileFromId($user, $exerciseData->exerciseProfileId);
        $this->profiles->assertExerciseProfileNotTampered($exerciseProfile, $exerciseData, $isSuperset);

        if ($exerciseData->deloadExerciseId !== null) {
            Exercise::assertAvailableFor($user, $exerciseData->deloadExerciseId);
        }

        $usesSupersetFingerprint = $isSuperset || $sharedProfile === null;
        $exerciseFingerprint = ExerciseProfileAssignment::exerciseFingerprint(
            $exerciseProfile,
            $usesSupersetFingerprint,
        );
        $exerciseAssignmentIsCurrent = $exerciseProfile !== null
            && ExerciseProfileAssignment::assignmentIsCurrent(
                $exerciseData->exerciseProfileFingerprint,
                $exerciseFingerprint,
            );
        $storedExerciseFingerprint = ExerciseProfileAssignment::storedExerciseFingerprint(
            $exerciseData->exerciseProfileFingerprint,
            $exerciseFingerprint,
        );

        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_profile_id' => $exerciseProfile?->id,
            'exercise_profile_fingerprint' => $storedExerciseFingerprint,
            'exercise_id' => $exerciseData->exerciseId,
            'position' => $position,
            'prescription_mode' => $exerciseData->prescriptionMode,
            'prescribed_duration_seconds' => $exerciseData->prescriptionMode === PrescriptionMode::Duration
                ? $exerciseData->prescribedDurationSeconds
                : null,
            'working_weight_g' => $exerciseData->workingWeightGrams(),
            'note' => self::normalizeNote($exerciseData->note),
            'deload_exercise_id' => $isCircuit ? null : $exerciseData->deloadExerciseId,
            'deload_working_weight_g' => $isCircuit ? null : $exerciseData->deloadWorkingWeightGrams(),
            'deload_note' => $isCircuit || $exerciseData->deloadExerciseId === null
                ? null
                : self::normalizeNote($exerciseData->deloadNote),
            'prescribed_reps' => $exerciseData->prescriptionMode === PrescriptionMode::Reps
                ? $exerciseData->prescribedReps
                : null,
            'achievement_floor_override' => $isCircuit || $exerciseData->prescriptionMode === PrescriptionMode::Duration
                ? null
                : $this->achievementFloorForStorage($exerciseData),
            'floor_is_derived' => $isCircuit || $exerciseData->prescriptionMode === PrescriptionMode::Duration
                ? null
                : $this->floorDerivationForAssignment(
                    $exerciseProfile,
                    $exerciseData,
                    $exerciseAssignmentIsCurrent,
                ),
            'progression_target_override' => $isCircuit || $exerciseData->prescriptionMode === PrescriptionMode::Duration
                ? null
                : $exerciseData->progressionTarget,
            'deload_weight_factor' => $this->deloadWeightFactorForStorage($exerciseProfile, $exerciseData),
            'deload_reps_factor' => $this->deloadRepsFactorForStorage($exerciseProfile, $exerciseData),
        ]);
    }

    private function deloadWeightFactorForStorage(?ExerciseProfile $exerciseProfile, SyncBlockExerciseData $exerciseData): float
    {
        if ($exerciseData->deloadWeightFactor !== null) {
            return $exerciseData->deloadWeightFactor;
        }

        if ($exerciseProfile !== null) {
            return (float) $exerciseProfile->deload_weight_factor;
        }

        return 0.5;
    }

    private function deloadRepsFactorForStorage(?ExerciseProfile $exerciseProfile, SyncBlockExerciseData $exerciseData): float
    {
        if ($exerciseData->deloadRepsFactor !== null) {
            return $exerciseData->deloadRepsFactor;
        }

        if ($exerciseProfile !== null) {
            return (float) $exerciseProfile->deload_reps_factor;
        }

        return 0.5;
    }

    private static function normalizeNote(?string $note): ?string
    {
        $normalized = trim((string) $note);

        return $normalized === '' ? null : $normalized;
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
}
