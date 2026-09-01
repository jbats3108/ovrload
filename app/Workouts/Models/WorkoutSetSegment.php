<?php

namespace App\Workouts\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

class WorkoutSetSegment extends Model
{
    #[Override]
    protected $fillable = [
        'workout_set_id',
        'position',
        'weight_g',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'weight_g' => 'integer',
        ];
    }
}
