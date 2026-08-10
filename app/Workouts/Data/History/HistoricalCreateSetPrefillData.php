<?php

namespace App\Workouts\Data\History;

use App\Routines\Models\RoutineBlockExercise;
use App\Shared\Support\Weight;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class HistoricalCreateSetPrefillData extends Data
{
    /**
     * @param  DataCollection<int, HistoricalCreateSegmentPrefillData>  $segments
     */
    public function __construct(
        public readonly int $exercisePosition,
        public readonly string $exerciseName,
        public readonly int $setIndex,
        public readonly bool $isDropset,
        public readonly ?float $weightKg,
        public readonly int $reps,
        #[DataCollectionOf(HistoricalCreateSegmentPrefillData::class)]
        public readonly DataCollection $segments,
    ) {}

    /**
     * @param  Collection<int, mixed>  $recipeSegments
     */
    public static function fromExercise(
        RoutineBlockExercise $exercise,
        int $setIndex,
        $recipeSegments,
    ): self {
        $isDropset = $recipeSegments->count() >= 2;

        $segments = $isDropset
            ? $recipeSegments->map(fn ($segment): HistoricalCreateSegmentPrefillData => new HistoricalCreateSegmentPrefillData(
                weightKg: Weight::gramsToKg($segment->weight_g),
            ))
            : collect();

        return new self(
            exercisePosition: $exercise->position,
            exerciseName: $exercise->exercise->getName(),
            setIndex: $setIndex,
            isDropset: $isDropset,
            weightKg: $isDropset ? null : Weight::gramsToKg($exercise->working_weight_g),
            reps: $exercise->prescribed_reps,
            segments: HistoricalCreateSegmentPrefillData::collect($segments, DataCollection::class),
        );
    }
}
