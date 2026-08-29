<?php

namespace App\Routines\Data;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Shared\Data\Validation\DeloadEveryN;
use App\Shared\Data\Validation\DeloadRepsFactor;
use App\Shared\Data\Validation\DeloadWeightFactor;
use App\Users\Models\User;
use Spatie\LaravelData\Attributes\FromAuthenticatedUser;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class StoreRoutineData extends Data
{
    public function __construct(
        #[Max(255)]
        public readonly string $name,

        #[Exists(ExerciseProfile::class, 'id')]
        public readonly int $defaultExerciseProfileId,

        #[FromAuthenticatedUser]
        public readonly User $user,

        #[DeloadWeightFactor]
        public readonly ?float $deloadWeightFactor = null,

        #[DeloadRepsFactor]
        public readonly ?float $deloadRepsFactor = null,

        #[DeloadEveryN]
        public readonly ?int $deloadEveryN = null,
    ) {}
}
