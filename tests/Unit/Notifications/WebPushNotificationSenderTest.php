<?php

namespace Tests\Unit\Notifications;

use App\Notifications\Exceptions\PushDeliveryException;
use App\Notifications\Models\PushSubscription;
use App\Notifications\Services\WebPushNotificationSender;
use Psr\Log\NullLogger;
use Tests\TestCase;

class WebPushNotificationSenderTest extends TestCase
{
    public function test_it_requires_vapid_credentials(): void
    {
        config()->set('services.web_push', []);

        $this->expectException(PushDeliveryException::class);

        (new WebPushNotificationSender(new NullLogger))->send(
            new PushSubscription,
            [
                'title' => 'Rest over',
                'body' => 'Time for the next set.',
                'url' => '/dashboard',
                'tag' => 'ovrload-rest-end',
            ],
        );
    }

    public function test_it_converts_a_stored_subscription_to_the_library_format(): void
    {
        $subscription = new PushSubscription([
            'endpoint' => 'https://push.example.test/endpoint',
            'public_key' => str_repeat('A', 87),
            'auth_token' => str_repeat('B', 22),
            'content_encoding' => 'aes128gcm',
        ]);

        $webPushSubscription = $subscription->toWebPushSubscription();

        $this->assertSame('https://push.example.test/endpoint', $webPushSubscription->getEndpoint());
        $this->assertSame(str_repeat('A', 87), $webPushSubscription->getPublicKey());
        $this->assertSame(str_repeat('B', 22), $webPushSubscription->getAuthToken());
        $this->assertSame('aes128gcm', $webPushSubscription->getContentEncoding());
    }
}
