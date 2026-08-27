<?php

namespace App\Workouts\Services;

use App\Notifications\Models\PushSubscription;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Jobs\SendRestNotificationJob;
use App\Workouts\Models\RestTimer;
use App\Workouts\Models\Workout;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RestTimerService
{
    public function start(Workout $workout, int $seconds): ?RestTimer
    {
        if ($seconds < 1 || $seconds > 3600) {
            throw new InvalidArgumentException('Rest timer must be between 1 and 3600 seconds.');
        }

        return DB::transaction(function () use ($workout, $seconds): ?RestTimer {
            $lockedWorkout = Workout::query()
                ->whereKey($workout->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedWorkout->status !== WorkoutStatus::InProgress) {
                return null;
            }

            $this->cancelForWorkout($lockedWorkout);

            if (! $this->canScheduleForUser($lockedWorkout->user_id)) {
                return null;
            }

            $timer = RestTimer::create([
                'workout_id' => $lockedWorkout->id,
                'ends_at' => now()->addSeconds($seconds),
            ]);

            SendRestNotificationJob::dispatch($timer->id)
                ->delay(now()->addSeconds(min($seconds, RestTimer::MAX_QUEUE_DELAY_SECONDS)))
                ->afterCommit();

            return $timer;
        });
    }

    public function cancel(RestTimer $timer): void
    {
        RestTimer::query()
            ->whereKey($timer->id)
            ->whereNull('cancelled_at')
            ->whereNull('sent_at')
            ->update(['cancelled_at' => now()]);
    }

    public function cancelForWorkout(Workout $workout): void
    {
        RestTimer::query()
            ->where('workout_id', $workout->id)
            ->whereNull('cancelled_at')
            ->whereNull('sent_at')
            ->update(['cancelled_at' => now()]);
    }

    private function canScheduleForUser(int $userId): bool
    {
        $config = config('services.web_push', []);
        if (! is_array($config)) {
            return false;
        }

        return is_string($config['subject'] ?? null)
            && $config['subject'] !== ''
            && is_string($config['public_key'] ?? null)
            && $config['public_key'] !== ''
            && is_string($config['private_key'] ?? null)
            && $config['private_key'] !== ''
            && PushSubscription::query()->where('user_id', $userId)->exists();
    }
}
