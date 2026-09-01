<?php

namespace App\Workouts\Models;

use App\Shared\Enums\WarmUpWeightMode;
use Illuminate\Database\Eloquent\Model;
use Override;

class WorkoutWarmUpStep extends Model
{
    #[Override]
    protected $fillable = [
        'workout_set_group_id',
        'position',
        'weight_mode',
        'percent_of_working',
        'weight_g',
        'reps',
        'has_setup_after',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'weight_mode' => WarmUpWeightMode::class,
            'percent_of_working' => 'integer',
            'weight_g' => 'integer',
            'reps' => 'integer',
            'has_setup_after' => 'boolean',
        ];
    }
}
