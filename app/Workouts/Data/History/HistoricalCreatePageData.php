<?php

namespace App\Workouts\Data\History;

use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class HistoricalCreatePageData extends Data
{
    /**
     * @param  DataCollection<int, HistoricalCreateBlockData>  $blocks
     */
    public function __construct(
        public readonly string $routineSlug,
        public readonly string $routineName,
        public readonly float $deloadWeightFactor,
        public readonly float $deloadRepsFactor,
        #[DataCollectionOf(HistoricalCreateBlockData::class)]
        public readonly DataCollection $blocks,
    ) {}

    public static function fromRoutine(Routine $routine): self
    {
        $routine->loadMissing(Routine::EDITOR_STRUCTURE_WITH_DELOAD);

        $blocks = $routine->blocks
            ->filter(fn (RoutineBlock $block): bool => $block->blockExercises->isNotEmpty())
            ->map(fn (RoutineBlock $block): HistoricalCreateBlockData => HistoricalCreateBlockData::fromRoutineBlock($block))
            ->values();

        return new self(
            routineSlug: $routine->slug,
            routineName: $routine->name,
            deloadWeightFactor: (float) $routine->deload_weight_factor,
            deloadRepsFactor: (float) $routine->deload_reps_factor,
            blocks: HistoricalCreateBlockData::collect($blocks, DataCollection::class),
        );
    }
}
