<?php

namespace App\ExerciseProfiles\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class ExerciseProfilePageData extends Data
{
    /**
     * @param  DataCollection<int, ExerciseProfileOptionData>  $profiles
     * @param  DataCollection<int, ExerciseProfileOptionData>  $archivedProfiles
     */
    public function __construct(
        public readonly ?int $defaultProfileId,

        #[DataCollectionOf(ExerciseProfileOptionData::class)]
        public readonly DataCollection $profiles,

        #[DataCollectionOf(ExerciseProfileOptionData::class)]
        public readonly DataCollection $archivedProfiles,
    ) {}
}
