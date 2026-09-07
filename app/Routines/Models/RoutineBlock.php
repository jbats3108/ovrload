<?php

namespace App\Routines\Models;

use App\Shared\Enums\BlockType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Override;

class RoutineBlock extends Model
{
    #[Override]
    protected $fillable = [
        'routine_id',
        'shared_exercise_profile_id',
        'shared_profile_fingerprint',
        'position',
        'type',
        'is_superset',
        'stage_rest_seconds',
        'has_setup_after',
        'has_setup_after_warm_up',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'shared_exercise_profile_id' => 'integer',
            'type' => BlockType::class,
            'is_superset' => 'boolean',
            'stage_rest_seconds' => 'integer',
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

    /** @return HasMany<RoutineBlockExercise, $this> */
    public function blockExercises(): HasMany
    {
        return $this->hasMany(RoutineBlockExercise::class)->orderBy('position');
    }

    /** @return HasMany<RoutineSetGroup, $this> */
    public function setGroups(): HasMany
    {
        return $this->hasMany(RoutineSetGroup::class);
    }

    /** @return HasOne<RoutineSetGroup, $this> */
    public function warmUpSetGroup(): HasOne
    {
        return $this->hasOne(RoutineSetGroup::class)->where('type', 'warm_up');
    }

    /** @return HasOne<RoutineSetGroup, $this> */
    public function workingSetGroup(): HasOne
    {
        return $this->hasOne(RoutineSetGroup::class)->where('type', 'working');
    }
}
