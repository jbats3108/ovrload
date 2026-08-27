<?php

namespace Tests\Feature\Workouts;

use App\Notifications\Contracts\PushNotificationSender;
use App\Notifications\Models\PushSubscription;
use App\Notifications\ValueObjects\PushDeliveryResult;
use App\Users\Models\User;
use App\Workouts\Enums\WorkoutStatus;
use App\Workouts\Jobs\SendRestNotificationJob;
use App\Workouts\Models\RestTimer;
use App\Workouts\Services\WorkoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\CreatesPlayableWorkout;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

final class RecordingPushNotificationSender implements PushNotificationSender
{
    public int $calls = 0;

    /** @var list<array{title: string, body: string, url: string, tag: string}> */
    public array $payloads = [];

    public function __construct(
        private readonly PushDeliveryResult $result,
    ) {}

    /**
     * @param  array{title: string, body: string, url: string, tag: string}  $payload
     */
    public function send(PushSubscription $subscription, array $payload): PushDeliveryResult
    {
        $this->calls++;
        $this->payloads[] = $payload;

        return $this->result;
    }
}

class RestTimerTest extends TestCase
{
    use CreatesPlayableWorkout;
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(withCatalogAndRoutines: false);
        $this->configureWebPush();
    }

    #[Test]
    public function it_schedules_a_server_notification_for_a_registered_user(): void
    {
        Queue::fake();
        $this->registerSubscription();
        $workout = $this->createPlayableWorkout();

        $response = $this->actingAs($this->user)
            ->postJson(route('workouts.rest-timers.start', $workout), ['seconds' => 90]);

        $response->assertCreated()->assertJsonStructure(['id', 'ends_at']);

        $timer = RestTimer::query()->firstOrFail();
        $this->assertModelExists($timer);
        $this->assertSame($workout->id, $timer->workout_id);
        $this->assertNotNull($timer->ends_at);
        Queue::assertPushed(SendRestNotificationJob::class, fn (SendRestNotificationJob $job): bool => $job->restTimerId === $timer->id);
    }

    #[Test]
    public function it_chunks_long_delays_for_managed_queue_compatibility(): void
    {
        Queue::fake();
        $this->registerSubscription();
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->user)
            ->postJson(route('workouts.rest-timers.start', $workout), ['seconds' => 3600])
            ->assertCreated();

        $timer = RestTimer::query()->firstOrFail();
        Queue::assertPushed(SendRestNotificationJob::class, function (SendRestNotificationJob $job) use ($timer): bool {
            return $job->restTimerId === $timer->id
                && $job->delay instanceof \DateTimeInterface
                && $job->delay->getTimestamp() <= now()->addSeconds(RestTimer::MAX_QUEUE_DELAY_SECONDS + 1)->getTimestamp();
        });
    }

    #[Test]
    public function it_returns_no_content_without_a_push_subscription(): void
    {
        Queue::fake();
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->user)
            ->postJson(route('workouts.rest-timers.start', $workout), ['seconds' => 90])
            ->assertNoContent();

        $this->assertSame(0, RestTimer::query()->count());
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_forbids_another_user_from_scheduling_a_workout_notification(): void
    {
        Queue::fake();
        $workout = $this->createPlayableWorkout();

        $this->actingAs($this->secondUser)
            ->postJson(route('workouts.rest-timers.start', $workout), ['seconds' => 90])
            ->assertForbidden();
    }

    #[Test]
    public function it_cancels_a_scheduled_notification(): void
    {
        Queue::fake();
        $this->registerSubscription();
        $workout = $this->createPlayableWorkout();

        $startResponse = $this->actingAs($this->user)
            ->postJson(route('workouts.rest-timers.start', $workout), ['seconds' => 90])
            ->assertCreated();
        $timerId = (int) $startResponse->json('id');
        $timer = RestTimer::query()->findOrFail($timerId);

        $this->actingAs($this->user)
            ->deleteJson(route('workouts.rest-timers.destroy', ['workout' => $workout, 'timer' => $timer]))
            ->assertNoContent();

        $this->assertNotNull(RestTimer::query()->whereKey($timerId)->value('cancelled_at'));
    }

    #[Test]
    public function it_forbids_another_user_from_cancelling_a_workout_notification(): void
    {
        Queue::fake();
        $this->registerSubscription();
        $workout = $this->createPlayableWorkout();
        $startResponse = $this->actingAs($this->user)
            ->postJson(route('workouts.rest-timers.start', $workout), ['seconds' => 90])
            ->assertCreated();
        $timer = RestTimer::query()->findOrFail((int) $startResponse->json('id'));

        $this->actingAs($this->secondUser)
            ->deleteJson(route('workouts.rest-timers.destroy', ['workout' => $workout, 'timer' => $timer]))
            ->assertForbidden();

        $this->assertNull(RestTimer::query()->whereKey($timer->id)->value('cancelled_at'));
    }

    #[Test]
    public function it_sends_and_marks_a_due_notification(): void
    {
        $subscription = $this->registerSubscription();
        $workout = $this->createPlayableWorkout();
        $timer = RestTimer::create([
            'workout_id' => $workout->id,
            'ends_at' => now()->subSecond(),
        ]);
        $sender = new RecordingPushNotificationSender(PushDeliveryResult::sent());

        (new SendRestNotificationJob($timer->id))->handle($sender);

        $this->assertSame(1, $sender->calls);
        $this->assertSame('Rest over', $sender->payloads[0]['title']);
        $this->assertNotNull(RestTimer::query()->whereKey($timer->id)->value('sent_at'));
    }

    #[Test]
    public function it_removes_expired_subscriptions_after_a_due_notification(): void
    {
        $subscription = $this->registerSubscription();
        $workout = $this->createPlayableWorkout();
        $timer = RestTimer::create([
            'workout_id' => $workout->id,
            'ends_at' => now()->subSecond(),
        ]);
        $sender = new RecordingPushNotificationSender(PushDeliveryResult::expired());

        (new SendRestNotificationJob($timer->id))->handle($sender);

        $this->assertModelMissing($subscription);
        $this->assertSame(1, $sender->calls);
        $this->assertNotNull(RestTimer::query()->whereKey($timer->id)->value('sent_at'));
    }

    #[Test]
    public function it_does_not_send_cancelled_notifications(): void
    {
        $this->registerSubscription();
        $workout = $this->createPlayableWorkout();
        $timer = RestTimer::create([
            'workout_id' => $workout->id,
            'ends_at' => now()->subSecond(),
            'cancelled_at' => now(),
        ]);
        $sender = new RecordingPushNotificationSender(PushDeliveryResult::sent());

        (new SendRestNotificationJob($timer->id))->handle($sender);

        $this->assertSame(0, $sender->calls);
        $this->assertNull(RestTimer::query()->whereKey($timer->id)->value('sent_at'));
    }

    #[Test]
    public function it_releases_a_job_again_when_it_runs_before_the_due_time(): void
    {
        $workout = $this->createPlayableWorkout();
        $timer = RestTimer::create([
            'workout_id' => $workout->id,
            'ends_at' => now()->addSeconds(3600),
        ]);
        $sender = new RecordingPushNotificationSender(PushDeliveryResult::sent());
        $job = (new SendRestNotificationJob($timer->id))->withFakeQueueInteractions();

        $job->handle($sender);

        $job->assertReleased(delay: RestTimer::MAX_QUEUE_DELAY_SECONDS);
    }

    #[Test]
    public function it_cancels_pending_notifications_when_a_workout_is_finished(): void
    {
        Queue::fake();
        $this->registerSubscription();
        $workout = $this->createPlayableWorkout();
        $this->actingAs($this->user)
            ->postJson(route('workouts.rest-timers.start', $workout), ['seconds' => 90])
            ->assertCreated();
        $timer = RestTimer::query()->firstOrFail();

        app(WorkoutService::class)->finishWorkout($workout);

        $this->assertNotNull(RestTimer::query()->whereKey($timer->id)->value('cancelled_at'));
        $this->assertSame(WorkoutStatus::Finished, $workout->fresh()->status);
    }

    private function registerSubscription(?User $user = null): PushSubscription
    {
        return ($user ?? $this->user)->pushSubscriptions()->create([
            'endpoint' => 'https://web.push.apple.com/3/device/example',
            'public_key' => str_repeat('A', 87),
            'auth_token' => str_repeat('B', 22),
            'content_encoding' => 'aes128gcm',
        ]);
    }

    private function configureWebPush(): void
    {
        config()->set('services.web_push', [
            'subject' => 'mailto:test@example.test',
            'public_key' => str_repeat('A', 87),
            'private_key' => str_repeat('B', 43),
            'connect_timeout' => 3,
            'timeout' => 10,
        ]);
    }
}
