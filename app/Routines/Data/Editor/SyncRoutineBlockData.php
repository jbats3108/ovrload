<?php

namespace App\Routines\Data\Editor;

use App\Shared\Enums\BlockType;
use Override;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class SyncRoutineBlockData extends Data
{
    /**
     * @param  DataCollection<int, SyncBlockExerciseData>  $exercises
     */
    public function __construct(
        public readonly bool $isSuperset,
        public readonly bool $hasSetupAfter,

        #[DataCollectionOf(SyncBlockExerciseData::class)]
        #[Min(1)]
        public readonly DataCollection $exercises,

        public readonly SyncSetGroupData $working,

        public readonly ?SyncWarmUpData $warmUp = null,

        public readonly bool $hasSetupAfterWarmUp = false,
        public readonly ?int $sharedProfileId = null,
        public readonly ?string $sharedProfileFingerprint = null,
        public readonly ?BlockType $type = null,

        #[Nullable, Min(0), Max(3600)]
        public readonly ?int $stageRestSeconds = null,
    ) {}

    public function blockType(): BlockType
    {
        if ($this->type !== null) {
            return $this->type;
        }

        return $this->isSuperset ? BlockType::Superset : BlockType::Single;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    #[Override]
    public static function prepareForPipeline(array $properties): array
    {
        return BlankRestSeconds::inBlock($properties);
    }
}
