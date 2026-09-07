<?php

namespace App\Workouts\Models;

use App\Shared\Enums\BlockType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Override;

class WorkoutBlock extends Model
{
    #[Override]
    protected $fillable = [
        'workout_id',
        'position',
        'type',
        'is_superset',
        'stage_rest_seconds',
        'is_ad_hoc',
        'is_parked',
        'has_setup_after',
        'has_setup_after_warm_up',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'type' => BlockType::class,
            'is_superset' => 'boolean',
            'stage_rest_seconds' => 'integer',
            'is_ad_hoc' => 'boolean',
            'is_parked' => 'boolean',
            'has_setup_after' => 'boolean',
            'has_setup_after_warm_up' => 'boolean',
        ];
    }

    public function isCircuit(): bool
    {
        return $this->type === BlockType::Circuit;
    }

    public function isSuperset(): bool
    {
        return $this->type === BlockType::Superset;
    }

    public function isSingle(): bool
    {
        return $this->type === BlockType::Single;
    }

    /** @return BelongsTo<Workout, $this> */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    /** @return HasMany<WorkoutBlockExercise, $this> */
    public function blockExercises(): HasMany
    {
        return $this->hasMany(WorkoutBlockExercise::class)->orderBy('position');
    }

    /** @return HasMany<WorkoutSetGroup, $this> */
    public function setGroups(): HasMany
    {
        return $this->hasMany(WorkoutSetGroup::class);
    }

    /** @return HasOne<WorkoutSetGroup, $this> */
    public function warmUpSetGroup(): HasOne
    {
        return $this->hasOne(WorkoutSetGroup::class)->where('type', 'warm_up');
    }

    /** @return HasOne<WorkoutSetGroup, $this> */
    public function workingSetGroup(): HasOne
    {
        return $this->hasOne(WorkoutSetGroup::class)->where('type', 'working');
    }

    public function assertBelongsToWorkout(Workout $workout): void
    {
        abort_unless($this->workout_id === $workout->id, 404);
    }
}
