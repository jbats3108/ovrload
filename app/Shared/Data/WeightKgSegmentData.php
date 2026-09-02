<?php

namespace App\Shared\Data;

use App\Shared\Support\Weight;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class WeightKgSegmentData extends Data
{
    public function __construct(
        #[Min(0)]
        public readonly float $weightKg,
    ) {}

    public function weightGrams(): int
    {
        return Weight::kgToGrams($this->weightKg);
    }

    /**
     * @param  DataCollection<int, self>  $segments
     * @return list<int>
     */
    public static function gramsList(DataCollection $segments): array
    {
        return array_map(
            static fn (self $segment): int => $segment->weightGrams(),
            array_values($segments->all()),
        );
    }
}
