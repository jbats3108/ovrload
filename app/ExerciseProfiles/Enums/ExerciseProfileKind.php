<?php

namespace App\ExerciseProfiles\Enums;

enum ExerciseProfileKind: string
{
    case Custom = 'custom';
    case Preset = 'preset';
}
