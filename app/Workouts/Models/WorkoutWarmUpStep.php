<?php

namespace App\Workouts\Models;

use App\Shared\Enums\WarmUpWeightMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

class WorkoutWarmUpStep extends Model
{
    #[Override]
    protected $fillable = [
        'workout_set_group_id',
        'position',
        'weight_mode',
        'percent_of_working',
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
            'reps' => 'integer',
            'has_setup_after' => 'boolean',
        ];
    }

    /** @return BelongsTo<WorkoutSetGroup, $this> */
    public function setGroup(): BelongsTo
    {
        return $this->belongsTo(WorkoutSetGroup::class, 'workout_set_group_id');
    }
}
