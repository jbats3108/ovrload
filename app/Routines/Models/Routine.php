<?php

namespace App\Routines\Models;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Routines\Services\RoutineSlugGenerator;
use App\Shared\Traits\HasName;
use App\Shared\Traits\HasSlug;
use App\Users\Models\User;
use App\Workouts\Models\Workout;
use Database\Factories\RoutineFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class Routine extends Model
{
    /** @var list<string> */
    public const EDITOR_STRUCTURE = [
        'blocks.blockExercises.exercise',
        'blocks.setGroups.warmUpSteps',
        'blocks.setGroups.dropsetSegments',
    ];

    /** @var list<string> */
    public const EDITOR_STRUCTURE_WITH_DELOAD = [
        'blocks.blockExercises.exercise',
        'blocks.blockExercises.deloadExercise',
        'blocks.setGroups.warmUpSteps',
        'blocks.setGroups.dropsetSegments',
    ];

    /** @var list<string> */
    public const SNAPSHOT_STRUCTURE = [
        'blocks.blockExercises.exercise',
        'blocks.blockExercises.deloadExercise',
        'blocks.blockExercises.exerciseProfile',
        'blocks.setGroups.warmUpSteps',
        'blocks.setGroups.dropsetSegments',
    ];

    /** @use HasFactory<RoutineFactory> */
    use HasFactory;

    use HasName;
    use HasSlug;
    use SoftDeletes;

    #[Override]
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'deload_weight_factor',
        'deload_reps_factor',
        'deload_every_n',
        'default_exercise_profile_id',
    ];

    #[Override]
    protected static function booted(): void
    {
        static::creating(function (Routine $routine): void {
            if ($routine->slug !== null && $routine->slug !== '') {
                return;
            }

            $user = $routine->user ?? User::query()->find($routine->user_id);
            if ($user === null) {
                return;
            }

            $routine->slug = RoutineSlugGenerator::forUser($user, (string) $routine->name);
        });
    }

    #[Override]
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'deload_weight_factor' => 'decimal:3',
            'deload_reps_factor' => 'decimal:3',
            'deload_every_n' => 'integer',
            'default_exercise_profile_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ExerciseProfile, $this> */
    public function defaultExerciseProfile(): BelongsTo
    {
        return $this->belongsTo(ExerciseProfile::class, 'default_exercise_profile_id');
    }

    /** @return HasMany<RoutineBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(RoutineBlock::class)->orderBy('position');
    }

    /** @return HasMany<Workout, $this> */
    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWithEditorStructure(Builder $query, bool $includeDeload = false): void
    {
        $query->with($includeDeload ? self::EDITOR_STRUCTURE_WITH_DELOAD : self::EDITOR_STRUCTURE);
    }

    protected static function newFactory(): RoutineFactory
    {
        return RoutineFactory::new();
    }
}
