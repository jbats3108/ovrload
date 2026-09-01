<?php

namespace App\ExerciseProfiles\Enums;

enum ExerciseProfileStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
