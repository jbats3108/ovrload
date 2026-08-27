<?php

namespace App\Workouts\Jobs;

use App\Notifications\Contracts\PushNotificationSender;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Models\RestTimer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendRestNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30, 120];

    public int $timeout = 30;

    public function __construct(
        public readonly int $restTimerId,
    ) {}

    public function handle(PushNotificationSender $sender): void
    {
        $timer = RestTimer::query()
            ->with('workout.user.pushSubscriptions')
            ->find($this->restTimerId);

        if ($timer === null || ! $timer->isPending()) {
            return;
        }

        if (! $timer->isDue()) {
            $this->release(min(
                RestTimer::MAX_QUEUE_DELAY_SECONDS,
                max(1, (int) now()->diffInSeconds($timer->ends_at)),
            ));

            return;
        }

        if ($timer->workout === null || $timer->workout->status !== WorkoutStatus::InProgress) {
            $timer->update(['cancelled_at' => now()]);

            return;
        }

        /** @var array{title: string, body: string, url: string, tag: string} $payload */
        $payload = [
            'title' => 'Rest over',
            'body' => 'Time for the next set.',
            'url' => route('workouts.play', $timer->workout, absolute: false),
            'tag' => 'ovrload-rest-end',
        ];

        foreach ($timer->workout->user->pushSubscriptions as $subscription) {
            $result = $sender->send($subscription, $payload);

            if ($result->expired) {
                $subscription->delete();
            }
        }

        RestTimer::query()
            ->whereKey($timer->id)
            ->whereNull('cancelled_at')
            ->whereNull('sent_at')
            ->update(['sent_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        RestTimer::query()
            ->whereKey($this->restTimerId)
            ->whereNull('sent_at')
            ->update(['failed_at' => now()]);

        Log::error('Rest notification job failed.', [
            'rest_timer_id' => $this->restTimerId,
            'exception' => $exception,
        ]);
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("rest-timer:{$this->restTimerId}"))->expireAfter(120),
        ];
    }
}
