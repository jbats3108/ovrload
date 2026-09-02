<?php

namespace App\Workouts\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Support\Validation\ValidationContext;

#[MapName(SnakeCaseMapper::class)]
class PlateStackData extends Data
{
    /**
     * @param  list<array{denomination_g: int, count: int}>  $perSide
     */
    public function __construct(
        #[Min(0), Max(100000)]
        public readonly int $barG,
        public readonly array $perSide = [],
    ) {}

    /**
     * @param  array{bar_g?: mixed, per_side?: mixed}|null  $snapshot
     */
    public static function fromSnapshot(?array $snapshot): ?self
    {
        if ($snapshot === null || ! isset($snapshot['bar_g'])) {
            return null;
        }

        /** @var list<array{denomination_g: int, count: int}> $perSide */
        $perSide = array_map(
            static fn (array $step): array => [
                'denomination_g' => (int) ($step['denomination_g'] ?? 0),
                'count' => (int) ($step['count'] ?? 0),
            ],
            is_array($snapshot['per_side'] ?? null) ? $snapshot['per_side'] : [],
        )
            |> array_values(...);

        return new self(
            barG: (int) $snapshot['bar_g'],
            perSide: $perSide,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rules(ValidationContext $context): array
    {
        return [
            'per_side' => ['array'],
            'per_side.*.denomination_g' => ['required', 'integer', 'min:1', 'max:100000'],
            'per_side.*.count' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array{bar_g: int, per_side: list<array{denomination_g: int, count: int}>}
     */
    public function snapshot(): array
    {
        return [
            'bar_g' => $this->barG,
            'per_side' => array_values(array_map(
                static fn (array $step): array => [
                    'denomination_g' => (int) $step['denomination_g'],
                    'count' => (int) $step['count'],
                ],
                $this->perSide,
            )),
        ];
    }
}
