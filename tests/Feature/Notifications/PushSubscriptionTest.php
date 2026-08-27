<?php

namespace Tests\Feature\Notifications;

use App\Notifications\Models\PushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(withCatalogAndRoutines: false);
    }

    #[Test]
    public function it_renders_notification_settings_with_the_public_vapid_key(): void
    {
        config()->set('services.web_push', [
            'subject' => 'mailto:test@example.test',
            'public_key' => 'public-key',
            'private_key' => 'private-key',
        ]);

        $this->user->pushSubscriptions()->create($this->subscriptionPayload());

        $this->actingAs($this->user)
            ->get(route('notifications.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Notifications')
                ->where('vapid_public_key', 'public-key')
                ->where('has_subscription', true));
    }

    #[Test]
    public function it_stores_an_encrypted_subscription_for_the_authenticated_user(): void
    {
        $payload = $this->subscriptionPayload();

        $this->actingAs($this->user)
            ->post(route('notifications.subscription.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success', 'Rest notifications enabled.');

        $subscription = PushSubscription::query()->firstOrFail();
        $this->assertModelExists($subscription);
        $this->assertSame($this->user->id, $subscription->user_id);
        $this->assertSame($payload['endpoint'], $subscription->endpoint);
        $this->assertSame($payload['public_key'], $subscription->public_key);
        $this->assertNotSame($payload['public_key'], DB::table('push_subscriptions')->value('public_key'));
    }

    #[Test]
    public function it_updates_a_subscription_when_the_same_endpoint_is_registered_again(): void
    {
        $payload = $this->subscriptionPayload();

        $this->actingAs($this->user)
            ->post(route('notifications.subscription.store'), $payload)
            ->assertRedirect();

        $payload['public_key'] = str_repeat('C', 87);

        $this->actingAs($this->user)
            ->post(route('notifications.subscription.store'), $payload)
            ->assertRedirect();

        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame($payload['public_key'], PushSubscription::query()->firstOrFail()->public_key);
    }

    #[Test]
    public function it_revokes_only_the_authenticated_users_subscription(): void
    {
        $subscription = $this->user->pushSubscriptions()->create($this->subscriptionPayload());

        $this->actingAs($this->user)
            ->delete(route('notifications.subscription.destroy'), [
                'endpoint' => $subscription->endpoint,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Rest notifications disabled.');

        $this->assertModelMissing($subscription);
    }

    #[Test]
    public function it_does_not_revoke_another_users_subscription(): void
    {
        $subscription = $this->secondUser->pushSubscriptions()->create($this->subscriptionPayload());

        $this->actingAs($this->user)
            ->delete(route('notifications.subscription.destroy'), [
                'endpoint' => $subscription->endpoint,
            ])
            ->assertRedirect();

        $this->assertModelExists($subscription);
    }

    #[Test]
    public function it_rejects_non_https_subscription_endpoints(): void
    {
        $this->actingAs($this->user)
            ->post(route('notifications.subscription.store'), [
                ...$this->subscriptionPayload(),
                'endpoint' => 'http://push.example.test/endpoint',
            ])
            ->assertSessionHasErrors('endpoint');
    }

    #[Test]
    public function it_rejects_https_endpoints_that_are_not_push_services(): void
    {
        $this->actingAs($this->user)
            ->post(route('notifications.subscription.store'), [
                ...$this->subscriptionPayload(),
                'endpoint' => 'https://example.test/push',
            ])
            ->assertSessionHasErrors('endpoint');
    }

    /**
     * @return array{endpoint: string, public_key: string, auth_token: string, content_encoding: string}
     */
    private function subscriptionPayload(): array
    {
        return [
            'endpoint' => 'https://web.push.apple.com/3/device/example',
            'public_key' => str_repeat('A', 87),
            'auth_token' => str_repeat('B', 22),
            'content_encoding' => 'aes128gcm',
        ];
    }
}
