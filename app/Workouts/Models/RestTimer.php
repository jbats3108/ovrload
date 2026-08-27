<?php

namespace App\Workouts\Models;

use Carbon\CarbonInterface;
use Database\Factories\Workouts\RestTimerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

class RestTimer extends Model
{
    /** @use HasFactory<RestTimerFactory> */
    use HasFactory;

    #[Override]
    protected $table = 'workout_rest_timers';

    public const int MAX_QUEUE_DELAY_SECONDS = 14 * 60;

    #[Override]
    protected $fillable = [
        'workout_id',
        'ends_at',
        'cancelled_at',
        'sent_at',
        'failed_at',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workout, $this> */
    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function isPending(): bool
    {
        return $this->cancelled_at === null && $this->sent_at === null && $this->failed_at === null;
    }

    public function isDue(?CarbonInterface $now = null): bool
    {
        return ($now ?? now())->greaterThanOrEqualTo($this->ends_at);
    }

    public function assertBelongsToWorkout(Workout $workout): void
    {
        abort_unless($this->workout_id === $workout->id, 404);
    }

    protected static function newFactory(): RestTimerFactory
    {
        return RestTimerFactory::new();
    }
}
