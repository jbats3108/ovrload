<?php

namespace App\Workouts\Models;

use Database\Factories\Workouts\WorkoutSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

class WorkoutSet extends Model
{
    /** @use HasFactory<WorkoutSetFactory> */
    use HasFactory;

    #[Override]
    protected $fillable = [
        'workout_set_group_id',
        'workout_block_exercise_id',
        'set_index',
        'reps',
        'weight_g',
        'plate_stack',
        'completed_at',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'set_index' => 'integer',
            'reps' => 'integer',
            'weight_g' => 'integer',
            'plate_stack' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WorkoutSetGroup, $this> */
    public function setGroup(): BelongsTo
    {
        return $this->belongsTo(WorkoutSetGroup::class, 'workout_set_group_id');
    }

    /** @return BelongsTo<WorkoutBlockExercise, $this> */
    public function blockExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutBlockExercise::class, 'workout_block_exercise_id');
    }

    /** @return HasMany<WorkoutSetSegment, $this> */
    public function segments(): HasMany
    {
        return $this->hasMany(WorkoutSetSegment::class)->orderBy('position');
    }

    public function isDropset(): bool
    {
        if ($this->relationLoaded('segments')) {
            return $this->segments->count() >= 2;
        }

        return $this->segments()->count() >= 2;
    }

    public function assertBelongsToWorkout(Workout $workout): void
    {
        $this->loadMissing('setGroup.block');
        abort_unless($this->setGroup->block->workout_id === $workout->id, 404);
    }

    /**
     * @param  list<int>  $weightGrams
     */
    public function replaceSegments(array $weightGrams): void
    {
        $this->segments()->delete();

        foreach ($weightGrams as $index => $grams) {
            WorkoutSetSegment::create([
                'workout_set_id' => $this->id,
                'position' => $index + 1,
                'weight_g' => $grams,
            ]);
        }
    }

    public function clearSegments(): void
    {
        $this->segments()->delete();
    }

    protected static function newFactory(): WorkoutSetFactory
    {
        return WorkoutSetFactory::new();
    }
}
