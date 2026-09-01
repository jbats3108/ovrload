<?php

namespace App\Exercises\Models;

use App\Exercises\Enums\ExerciseEquipment;
use App\MuscleGroups\Models\MuscleGroup;
use App\Shared\Traits\HasName;
use App\Shared\Traits\HasSlug;
use App\Users\Models\User;
use Database\Factories\ExerciseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use Override;

class Exercise extends Model
{
    /** @use HasFactory<ExerciseFactory> */
    use HasFactory;

    use HasName;
    use HasSlug;
    use SoftDeletes;

    #[Override]
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'primary_muscle_group_id',
        'secondary_muscle_group_id',
        'equipment',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'equipment' => ExerciseEquipment::class,
        ];
    }

    /** @return BelongsTo<MuscleGroup, $this> */
    public function primaryMuscleGroup(): BelongsTo
    {
        return $this->belongsTo(MuscleGroup::class, 'primary_muscle_group_id');
    }

    /** @return BelongsTo<MuscleGroup, $this> */
    public function secondaryMuscleGroup(): BelongsTo
    {
        return $this->belongsTo(MuscleGroup::class, 'secondary_muscle_group_id');
    }

    public function isCustom(): bool
    {
        return $this->user_id !== null;
    }

    public function isShared(): bool
    {
        return $this->user_id === null;
    }

    /**
     * @param  Builder<Exercise>  $query
     * @return Builder<Exercise>
     */
    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNull('user_id');
    }

    /**
     * @param  Builder<Exercise>  $query
     * @return Builder<Exercise>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user): void {
            $q->whereNull('user_id')->orWhere('user_id', $user->id);
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertAvailableFor(User $user, int $exerciseId): void
    {
        $exists = static::query()
            ->forUser($user)
            ->whereKey($exerciseId)
            ->exists();

        if (! $exists) {
            throw new InvalidArgumentException("Exercise {$exerciseId} is not available for this routine.");
        }
    }

    protected static function newFactory(): ExerciseFactory
    {
        return ExerciseFactory::new();
    }
}
