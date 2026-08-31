<?php

namespace App\Routines\Data\Editor;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Shared\Data\Validation\DeloadEveryN;
use App\Shared\Data\Validation\DeloadRepsFactor;
use App\Shared\Data\Validation\DeloadWeightFactor;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class SyncRoutineData extends Data
{
    /**
     * @param  DataCollection<int, SyncRoutineBlockData>|null  $blocks
     */
    public function __construct(
        #[Max(255)]
        public readonly string $name,

        #[DeloadWeightFactor]
        public readonly ?float $deloadWeightFactor = null,

        #[DeloadRepsFactor]
        public readonly ?float $deloadRepsFactor = null,

        #[DeloadEveryN]
        public readonly ?int $deloadEveryN = null,

        #[Exists(ExerciseProfile::class, 'id')]
        public readonly ?int $defaultExerciseProfileId = null,

        #[DataCollectionOf(SyncRoutineBlockData::class)]
        public readonly ?DataCollection $blocks = null,

        public readonly ?string $expectedUpdatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    #[Override]
    public static function prepareForPipeline(array $properties): array
    {
        return BlankRestSeconds::inRoutinePayload($properties);
    }
}
