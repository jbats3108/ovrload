<?php

namespace App\Exercises\Data;

use App\Exercises\Enums\ExerciseEquipment;
use App\MuscleGroups\Models\MuscleGroup;
use App\Shared\Data\Casts\SlugToModelCast;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Different;
use Spatie\LaravelData\Attributes\Validation\Enum;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class StoreCustomExerciseData extends Data
{
    public function __construct(
        #[Max(255)]
        public readonly string $name,

        #[Exists(MuscleGroup::class, 'slug')]
        #[WithCast(SlugToModelCast::class, MuscleGroup::class)]
        public readonly MuscleGroup $primaryMuscleGroup,

        #[Different('primaryMuscleGroup')]
        #[Exists(MuscleGroup::class, 'slug')]
        #[WithCast(SlugToModelCast::class, MuscleGroup::class)]
        public readonly ?MuscleGroup $secondaryMuscleGroup = null,

        #[Enum(ExerciseEquipment::class)]
        public readonly ?ExerciseEquipment $equipment = null,
    ) {}
}
