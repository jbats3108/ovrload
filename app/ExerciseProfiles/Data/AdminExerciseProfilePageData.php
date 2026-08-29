<?php

namespace App\ExerciseProfiles\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class AdminExerciseProfilePageData extends Data
{
    /**
     * @param  DataCollection<int, AdminExerciseProfileData>  $drafts
     * @param  DataCollection<int, AdminExerciseProfileData>  $published
     */
    public function __construct(
        #[DataCollectionOf(AdminExerciseProfileData::class)]
        public readonly DataCollection $drafts,

        #[DataCollectionOf(AdminExerciseProfileData::class)]
        public readonly DataCollection $published,
    ) {}
}
