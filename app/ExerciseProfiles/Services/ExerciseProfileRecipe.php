<?php

namespace App\ExerciseProfiles\Services;

final readonly class ExerciseProfileRecipe
{
    /**
     * @param  list<array{percent: int, reps: int}>  $warmUpSteps
     */
    public function __construct(
        public int $targetReps,
        public ?int $floorOverride,
        public int $workingRestSeconds,
        public array $warmUpSteps,
    ) {}

    public function resolvedFloor(): int
    {
        return $this->floorOverride ?? max(1, $this->targetReps - 2);
    }

    /**
     * @return array{
     *     target_reps: int,
     *     floor_override: int|null,
     *     working_rest_seconds: int,
     *     warm_up_steps: list<array{percent: int, reps: int}>
     * }
     */
    public function canonicalPayload(): array
    {
        return [
            'target_reps' => $this->targetReps,
            'floor_override' => $this->floorOverride,
            'working_rest_seconds' => $this->workingRestSeconds,
            'warm_up_steps' => array_values(array_map(
                static fn (array $step): array => [
                    'percent' => (int) $step['percent'],
                    'reps' => (int) $step['reps'],
                ],
                $this->warmUpSteps,
            )),
        ];
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->canonicalPayload(), JSON_THROW_ON_ERROR));
    }

    public function exerciseFingerprint(): string
    {
        return hash('sha256', json_encode([
            'target_reps' => $this->targetReps,
            'floor' => $this->resolvedFloor(),
        ], JSON_THROW_ON_ERROR));
    }

    public function sharedFingerprint(): string
    {
        return hash('sha256', json_encode([
            'working_rest_seconds' => $this->workingRestSeconds,
            'warm_up_steps' => $this->canonicalPayload()['warm_up_steps'],
        ], JSON_THROW_ON_ERROR));
    }
}
