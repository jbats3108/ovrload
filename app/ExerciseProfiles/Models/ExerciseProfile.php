<?php

namespace App\ExerciseProfiles\Models;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Services\ExerciseProfileRecipe;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Users\Models\User;
use Database\Factories\ExerciseProfiles\Models\ExerciseProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

class ExerciseProfile extends Model
{
    /** @use HasFactory<ExerciseProfileFactory> */
    use HasFactory;

    /** @var list<string> */
    #[Override]
    protected $fillable = [
        'user_id',
        'created_by_user_id',
        'kind',
        'status',
        'name',
        'slug',
        'slug_scope',
        'target_reps',
        'floor_override',
        'working_rest_seconds',
        'warm_up_steps',
        'recipe_fingerprint',
        'published_at',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => ExerciseProfileKind::class,
            'status' => ExerciseProfileStatus::class,
            'target_reps' => 'integer',
            'floor_override' => 'integer',
            'working_rest_seconds' => 'integer',
            'warm_up_steps' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<User, $this> */
    public function defaultedByUsers(): HasMany
    {
        return $this->hasMany(User::class, 'default_exercise_profile_id');
    }

    /** @return HasMany<Routine, $this> */
    public function defaultedByRoutines(): HasMany
    {
        return $this->hasMany(Routine::class, 'default_exercise_profile_id');
    }

    /** @return HasMany<RoutineBlock, $this> */
    public function sharedByBlocks(): HasMany
    {
        return $this->hasMany(RoutineBlock::class, 'shared_exercise_profile_id');
    }

    /** @return HasMany<RoutineBlockExercise, $this> */
    public function assignedToExercises(): HasMany
    {
        return $this->hasMany(RoutineBlockExercise::class, 'exercise_profile_id');
    }

    public function isPreset(): bool
    {
        return $this->kind === ExerciseProfileKind::Preset;
    }

    public function isCustom(): bool
    {
        return $this->kind === ExerciseProfileKind::Custom;
    }

    public function isPublished(): bool
    {
        return $this->status === ExerciseProfileStatus::Published;
    }

    public function isArchived(): bool
    {
        return $this->status === ExerciseProfileStatus::Archived;
    }

    public function isSelectable(): bool
    {
        return $this->isPublished();
    }

    public function displayName(): string
    {
        return $this->isPreset() ? 'OVRLOAD '.$this->name : $this->name;
    }

    public function recipe(): ExerciseProfileRecipe
    {
        return new ExerciseProfileRecipe(
            targetReps: $this->target_reps,
            floorOverride: $this->floor_override,
            workingRestSeconds: $this->working_rest_seconds,
            warmUpSteps: $this->warmUpStepList(),
        );
    }

    /**
     * @return list<array{percent: int, reps: int}>
     */
    public function warmUpStepList(): array
    {
        $steps = is_array($this->warm_up_steps) ? $this->warm_up_steps : [];

        return array_values(array_map(
            static fn (mixed $step): array => [
                'percent' => (int) (is_array($step) ? ($step['percent'] ?? 0) : 0),
                'reps' => (int) (is_array($step) ? ($step['reps'] ?? 0) : 0),
            ],
            $steps,
        ));
    }

    public function resolvedFloor(): int
    {
        return $this->recipe()->resolvedFloor();
    }

    protected static function newFactory(): ExerciseProfileFactory
    {
        return ExerciseProfileFactory::new();
    }
}
