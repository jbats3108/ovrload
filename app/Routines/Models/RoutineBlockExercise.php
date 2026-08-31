<?php

namespace App\Routines\Models;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Models\Exercise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

class RoutineBlockExercise extends Model
{
    #[Override]
    protected $fillable = [
        'routine_block_id',
        'exercise_profile_id',
        'exercise_profile_fingerprint',
        'exercise_id',
        'position',
        'working_weight_g',
        'deload_exercise_id',
        'deload_working_weight_g',
        'prescribed_reps',
        'achievement_floor_override',
        'floor_is_derived',
        'progression_target_override',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'exercise_profile_id' => 'integer',
            'working_weight_g' => 'integer',
            'deload_working_weight_g' => 'integer',
            'prescribed_reps' => 'integer',
            'achievement_floor_override' => 'integer',
            'floor_is_derived' => 'boolean',
            'progression_target_override' => 'integer',
        ];
    }

    /** @return BelongsTo<RoutineBlock, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(RoutineBlock::class, 'routine_block_id');
    }

    /** @return BelongsTo<ExerciseProfile, $this> */
    public function exerciseProfile(): BelongsTo
    {
        return $this->belongsTo(ExerciseProfile::class, 'exercise_profile_id');
    }

    /** @return BelongsTo<Exercise, $this> */
    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /** @return BelongsTo<Exercise, $this> */
    public function deloadExercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class, 'deload_exercise_id');
    }

    public function hasDeloadAlternate(): bool
    {
        return $this->deload_exercise_id !== null;
    }
}
