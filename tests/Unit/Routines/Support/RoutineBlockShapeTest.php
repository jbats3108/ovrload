<?php

namespace Tests\Unit\Routines\Support;

use App\Routines\Data\Editor\SyncBlockExerciseData;
use App\Routines\Data\Editor\SyncDropsetData;
use App\Routines\Data\Editor\SyncWarmUpStepData;
use App\Routines\Support\RoutineBlockShape;
use App\Shared\Data\WeightKgSegmentData;
use App\Shared\Enums\BlockType;
use App\Shared\Enums\PrescriptionMode;
use App\Shared\Enums\WarmUpWeightMode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\DataCollection;
use Tests\TestCase;

class RoutineBlockShapeTest extends TestCase
{
    #[Test]
    public function assert_accepts_a_valid_single_block(): void
    {
        RoutineBlockShape::assert(
            BlockType::Single,
            [$this->exercise()],
            [],
            [],
            null,
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_accepts_a_valid_superset(): void
    {
        RoutineBlockShape::assert(
            BlockType::Superset,
            [$this->exercise(1), $this->exercise(2)],
            [],
            [],
            null,
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_accepts_a_valid_circuit(): void
    {
        RoutineBlockShape::assert(
            BlockType::Circuit,
            [$this->exercise(1), $this->exercise(2), $this->exercise(3)],
            [],
            [],
            null,
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assert_rejects_single_with_wrong_exercise_count(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A non-superset must have exactly one exercise.');

        RoutineBlockShape::assert(
            BlockType::Single,
            [$this->exercise(1), $this->exercise(2)],
            [],
            [],
            null,
        );
    }

    #[Test]
    public function assert_rejects_superset_with_wrong_exercise_count(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A superset must have exactly two exercises.');

        RoutineBlockShape::assert(
            BlockType::Superset,
            [$this->exercise()],
            [],
            [],
            null,
        );
    }

    #[Test]
    public function assert_accepts_one_and_two_station_circuits(): void
    {
        RoutineBlockShape::assert(
            BlockType::Circuit,
            [$this->exercise(1)],
            [],
            [],
            null,
        );

        RoutineBlockShape::assert(
            BlockType::Circuit,
            [$this->exercise(1), $this->exercise(2)],
            [],
            [],
            null,
        );

        $this->addToAssertionCount(2);
    }

    #[Test]
    public function assert_rejects_circuit_with_no_exercises(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A circuit must have at least one exercise.');

        RoutineBlockShape::assert(
            BlockType::Circuit,
            [],
            [],
            [],
            null,
        );
    }

    #[Test]
    public function assert_rejects_timed_exercises_on_supersets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timed exercises are not supported on supersets.');

        RoutineBlockShape::assert(
            BlockType::Superset,
            [$this->exercise(1), $this->timedExercise(2)],
            [],
            [],
            null,
        );
    }

    #[Test]
    public function assert_rejects_dropsets_on_timed_singles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dropsets are not supported on timed exercises.');

        RoutineBlockShape::assert(
            BlockType::Single,
            [$this->timedExercise()],
            [$this->dropset()],
            [],
            null,
        );
    }

    #[Test]
    public function assert_timed_exercise_rejects_progression_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Automatic progression targets are not supported on timed exercises.');

        RoutineBlockShape::assertTimedExercise($this->timedExercise(progressionTarget: 8));
    }

    #[Test]
    public function assert_timed_exercise_rejects_achievement_floor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Achievement floor overrides are not supported on timed exercises.');

        RoutineBlockShape::assertTimedExercise($this->timedExercise(achievementFloor: 5));
    }

    #[Test]
    public function assert_rejects_shared_profile_on_circuits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Shared exercise profiles are not supported on circuits.');

        RoutineBlockShape::assert(
            BlockType::Circuit,
            [$this->exercise(1), $this->exercise(2), $this->exercise(3)],
            [],
            [],
            9,
        );
    }

    #[Test]
    public function assert_rejects_dropsets_on_supersets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dropsets are not supported on supersets.');

        RoutineBlockShape::assert(
            BlockType::Superset,
            [$this->exercise(1), $this->exercise(2)],
            [$this->dropset()],
            [],
            null,
        );
    }

    #[Test]
    public function assert_rejects_dropsets_on_circuits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dropsets are not supported on circuits.');

        RoutineBlockShape::assert(
            BlockType::Circuit,
            [$this->exercise(1), $this->exercise(2), $this->exercise(3)],
            [$this->dropset()],
            [],
            null,
        );
    }

    #[Test]
    public function assert_rejects_warm_up_steps_on_circuits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Warm-up steps are not supported on circuits.');

        RoutineBlockShape::assert(
            BlockType::Circuit,
            [$this->exercise(1), $this->exercise(2), $this->exercise(3)],
            [],
            [new SyncWarmUpStepData(mode: WarmUpWeightMode::Percent, percent: 50, reps: 5)],
            null,
        );
    }

    #[Test]
    public function assert_circuit_exercise_rejects_profile_assignment(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Exercise profiles are not supported on circuits.');

        RoutineBlockShape::assertCircuitExercise($this->exercise(exerciseProfileId: 3));
    }

    #[Test]
    public function assert_circuit_exercise_rejects_deload_alternate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Deload alternate exercises are not supported on circuits.');

        RoutineBlockShape::assertCircuitExercise($this->exercise(deloadExerciseId: 2, deloadWorkingWeightKg: 40.0));
    }

    #[Test]
    public function assert_circuit_exercise_rejects_progression_target(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Automatic progression targets are not supported on circuits.');

        RoutineBlockShape::assertCircuitExercise($this->exercise(progressionTarget: 8));
    }

    #[Test]
    public function assert_circuit_exercise_rejects_achievement_floor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Achievement floor overrides are not supported on circuits.');

        RoutineBlockShape::assertCircuitExercise($this->exercise(achievementFloor: 5));
    }

    private function exercise(
        int $exerciseId = 1,
        ?int $exerciseProfileId = null,
        ?int $deloadExerciseId = null,
        ?float $deloadWorkingWeightKg = null,
        ?int $progressionTarget = null,
        ?int $achievementFloor = null,
    ): SyncBlockExerciseData {
        return new SyncBlockExerciseData(
            exerciseId: $exerciseId,
            workingWeightKg: 60.0,
            prescribedReps: 6,
            achievementFloor: $achievementFloor,
            progressionTarget: $progressionTarget,
            exerciseProfileId: $exerciseProfileId,
            deloadExerciseId: $deloadExerciseId,
            deloadWorkingWeightKg: $deloadWorkingWeightKg,
        );
    }

    private function timedExercise(
        int $exerciseId = 1,
        ?int $progressionTarget = null,
        ?int $achievementFloor = null,
    ): SyncBlockExerciseData {
        return new SyncBlockExerciseData(
            exerciseId: $exerciseId,
            workingWeightKg: 0.0,
            prescribedReps: null,
            prescriptionMode: PrescriptionMode::Duration,
            prescribedDurationSeconds: 30,
            achievementFloor: $achievementFloor,
            progressionTarget: $progressionTarget,
        );
    }

    private function dropset(): SyncDropsetData
    {
        return new SyncDropsetData(
            setIndex: 0,
            segments: new DataCollection(WeightKgSegmentData::class, [
                new WeightKgSegmentData(weightKg: 60.0),
                new WeightKgSegmentData(weightKg: 50.0),
            ]),
        );
    }
}
