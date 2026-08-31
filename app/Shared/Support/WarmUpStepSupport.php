<?php

namespace App\Shared\Support;

use App\Exercises\Enums\ExerciseEquipment;
use App\Shared\Enums\WarmUpWeightMode;

final class WarmUpStepSupport
{
    /**
     * @param  array<string, mixed>  $step
     * @return array{mode: WarmUpWeightMode, percent: ?int, reps: int}|null
     */
    public static function normalize(mixed $step): ?array
    {
        if (! is_array($step)) {
            return null;
        }

        $reps = (int) ($step['reps'] ?? 0);
        if ($reps < 1) {
            return null;
        }

        $modeValue = $step['mode'] ?? WarmUpWeightMode::Percent->value;
        if ($modeValue instanceof WarmUpWeightMode) {
            $mode = $modeValue;
        } else {
            $mode = WarmUpWeightMode::tryFrom((string) $modeValue) ?? WarmUpWeightMode::Percent;
        }
        $percent = array_key_exists('percent', $step) ? (int) $step['percent'] : null;

        if ($mode === WarmUpWeightMode::Percent && ($percent === null || $percent < 1)) {
            return null;
        }

        return [
            'mode' => $mode,
            'percent' => $mode === WarmUpWeightMode::Bar ? null : $percent,
            'reps' => $reps,
        ];
    }

    /**
     * @param  list<mixed>  $steps
     * @return list<array{mode: WarmUpWeightMode, percent: ?int, reps: int}>
     */
    public static function normalizeList(array $steps): array
    {
        return array_values(array_filter(
            array_map(self::normalize(...), $steps),
            static fn (?array $step): bool => $step !== null,
        ));
    }

    /**
     * @param  array{mode: WarmUpWeightMode, percent: ?int, reps: int}  $step
     * @return array{mode: string, percent?: int, reps: int}
     */
    public static function toStorage(array $step): array
    {
        if ($step['mode'] === WarmUpWeightMode::Bar) {
            return [
                'mode' => WarmUpWeightMode::Bar->value,
                'reps' => $step['reps'],
            ];
        }

        return [
            'mode' => WarmUpWeightMode::Percent->value,
            'percent' => (int) $step['percent'],
            'reps' => $step['reps'],
        ];
    }

    public static function targetWeightG(
        WarmUpWeightMode $mode,
        ?int $percentOfWorking,
        int $workingWeightG,
        ?int $defaultBarWeightG,
        ?ExerciseEquipment $equipment,
    ): ?int {
        if ($mode === WarmUpWeightMode::Bar) {
            if ($equipment?->usesBarbellPlates() !== true || $defaultBarWeightG === null) {
                return null;
            }

            return $defaultBarWeightG;
        }

        if ($percentOfWorking === null || $percentOfWorking < 1) {
            return null;
        }

        return (int) round($workingWeightG * ($percentOfWorking / 100));
    }
}
