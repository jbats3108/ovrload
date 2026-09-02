<?php

namespace App\Users\Models;

use App\Auth\Models\RegistrationInvite;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Shared\Support\WarmUpStepSupport;
use App\Users\Enums\ProgressionStyle;
use App\Users\Enums\ProgressiveMidBlock;
use App\Users\Enums\WarmUpDefaultsScope;
use App\Users\Enums\WeightUnit;
use App\Workouts\Models\Workout;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Override;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    /** @var list<string> */
    #[Override]
    protected $fillable = [
        'name',
        'email',
        'password',
        'weight_unit',
        'achievement_floor_default',
        'progression_target_default',
        'progression_style_default',
        'progressive_mid_block_default',
        'deload_weight_factor_default',
        'deload_reps_factor_default',
        'deload_every_n_default',
        'warm_up_steps_default',
        'warm_up_defaults_scope',
        'default_exercise_profile_id',
    ];

    /** @var list<string> */
    #[Override]
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'weight_unit' => WeightUnit::class,
            'achievement_floor_default' => 'integer',
            'progression_target_default' => 'integer',
            'progression_style_default' => ProgressionStyle::class,
            'progressive_mid_block_default' => ProgressiveMidBlock::class,
            'deload_weight_factor_default' => 'decimal:3',
            'deload_reps_factor_default' => 'decimal:3',
            'deload_every_n_default' => 'integer',
            'warm_up_steps_default' => 'array',
            'warm_up_defaults_scope' => WarmUpDefaultsScope::class,
            'default_exercise_profile_id' => 'integer',
        ];
    }

    /**
     * App-wide warm-up ladder when the user has not set prefs yet.
     *
     * @return list<array{mode: string, percent?: int, reps: int}>
     */
    public static function fallbackWarmUpSteps(): array
    {
        return [
            ['mode' => 'percent', 'percent' => 40, 'reps' => 5],
            ['mode' => 'percent', 'percent' => 60, 'reps' => 3],
            ['mode' => 'percent', 'percent' => 80, 'reps' => 1],
        ];
    }

    /**
     * Steps to seed into new routine blocks. Null column → app fallback; empty list → no warm-up.
     *
     * @return list<array{mode: string, percent?: int, reps: int}>
     */
    public function resolvedWarmUpStepsDefault(): array
    {
        if ($this->warm_up_steps_default === null) {
            return self::fallbackWarmUpSteps();
        }

        return array_map(
            WarmUpStepSupport::toStorage(...),
            WarmUpStepSupport::normalizeList(array_values($this->warm_up_steps_default)),
        )
            |> array_values(...);
    }

    /**
     * Default Target (prescribed) reps for new editor blocks and Play ad-hoc exercises.
     * Stored as progression_target_default; null column → app fallback of 6.
     */
    public function resolvedDefaultTargetReps(): int
    {
        $value = $this->progression_target_default;

        return $value !== null && $value >= 1 ? $value : 6;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    #[Override]
    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            // Routines/workouts soft-delete; hard-delete so restrictOnDelete exercise FKs clear
            // before customs are removed. Otherwise user delete nulls user_id and customs become shared.
            $user->workouts()->withTrashed()->get()->each->forceDelete();
            $user->routines()->withTrashed()->get()->each->forceDelete();
            $user->customExercises()->withTrashed()->forceDelete();

            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            RegistrationInvite::query()
                ->where(function ($query) use ($user): void {
                    $query->where('email', $user->email)
                        ->orWhere('used_by', $user->id);
                })
                ->delete();
        });
    }

    /** @return HasMany<Routine, $this> */
    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    /** @return HasMany<Exercise, $this> */
    public function customExercises(): HasMany
    {
        return $this->hasMany(Exercise::class);
    }

    /** @return HasMany<ExerciseProfile, $this> */
    public function exerciseProfiles(): HasMany
    {
        return $this->hasMany(ExerciseProfile::class);
    }

    /** @return BelongsTo<ExerciseProfile, $this> */
    public function defaultExerciseProfile(): BelongsTo
    {
        return $this->belongsTo(ExerciseProfile::class, 'default_exercise_profile_id');
    }

    /** @return HasMany<Workout, $this> */
    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    /** @return HasOne<PlateProfile, $this> */
    public function plateProfile(): HasOne
    {
        return $this->hasOne(PlateProfile::class);
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
