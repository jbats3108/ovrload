<?php

namespace App\Routines\Data;

use App\Routines\Data\Editor\SyncDropsetData;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineSetGroup;
use App\Shared\Data\WeightKgSegmentData;
use App\Shared\Enums\SetGroupType;
use App\Shared\Support\Weight;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class RoutineBlockStructureData extends Data
{
    /**
     * @param  Collection<int, RoutineBlockExercise>  $blockExercises
     * @param  DataCollection<int, SyncDropsetData>  $dropsets
     */
    public function __construct(
        public readonly int $position,
        public readonly bool $isSuperset,
        public readonly bool $hasSetupAfter,
        public readonly bool $hasSetupAfterWarmUp,
        public readonly ?int $sharedProfileId,
        public readonly ?string $sharedProfileFingerprint,
        public readonly Collection $blockExercises,
        public readonly ?RoutineSetGroup $workingGroup,
        public readonly ?RoutineSetGroup $warmUpGroup,
        #[DataCollectionOf(SyncDropsetData::class)]
        public readonly DataCollection $dropsets,
    ) {}

    public static function fromRoutineBlock(RoutineBlock $block): self
    {
        $working = $block->setGroups->firstWhere('type', SetGroupType::Working);
        $warmUp = $block->setGroups->firstWhere('type', SetGroupType::WarmUp);

        $dropsets = collect($working !== null ? $working->dropsetSegments : [])
            ->groupBy('set_index')
            ->filter(fn ($segments): bool => $segments->count() >= 2)
            ->map(fn ($segments, $setIndex): SyncDropsetData => new SyncDropsetData(
                setIndex: (int) $setIndex,
                segments: WeightKgSegmentData::collect(
                    $segments->sortBy('position')->values()->map(
                        fn ($segment): WeightKgSegmentData => new WeightKgSegmentData(
                            weightKg: Weight::gramsToKg($segment->weight_g),
                        )
                    ),
                    DataCollection::class,
                ),
            ))
            ->values();

        return new self(
            position: $block->position,
            isSuperset: $block->is_superset,
            hasSetupAfter: $block->has_setup_after,
            hasSetupAfterWarmUp: $block->has_setup_after_warm_up,
            sharedProfileId: $block->shared_exercise_profile_id,
            sharedProfileFingerprint: $block->shared_profile_fingerprint,
            blockExercises: $block->blockExercises,
            workingGroup: $working instanceof RoutineSetGroup ? $working : null,
            warmUpGroup: $warmUp instanceof RoutineSetGroup ? $warmUp : null,
            dropsets: SyncDropsetData::collect($dropsets, DataCollection::class),
        );
    }
}
