<?php

namespace App\ExerciseProfiles\Enums;

enum ExerciseProfileKind: string
{
    case Custom = 'custom';
    case Preset = 'preset';

    public function isPreset(): bool
    {
        return $this === self::Preset;
    }
}
