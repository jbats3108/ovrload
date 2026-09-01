<?php

namespace App\Workouts\Models;

use App\Routines\Models\RoutineBlockExercise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

class BumpRecord extends Model
{
    #[Override]
    protected $fillable = [
        'workout_id',
        'routine_block_exercise_id',
        'from_weight_g',
        'to_weight_g',
        'undone_at',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'from_weight_g' => 'integer',
            'to_weight_g' => 'integer',
            'undone_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RoutineBlockExercise, $this> */
    public function routineBlockExercise(): BelongsTo
    {
        return $this->belongsTo(RoutineBlockExercise::class);
    }

    public function isActive(): bool
    {
        return $this->undone_at === null;
    }
}
