<?php

namespace App\Notifications\Services;

use App\Notifications\Contracts\PushNotificationSender;
use App\Notifications\Exceptions\PushDeliveryException;
use App\Notifications\Models\PushSubscription;
use App\Notifications\ValueObjects\PushDeliveryResult;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Throwable;

final class WebPushNotificationSender implements PushNotificationSender
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param  array{title: string, body: string, url: string, tag: string}  $payload
     */
    public function send(PushSubscription $subscription, array $payload): PushDeliveryResult
    {
        $config = config('services.web_push', []);
        if (! is_array($config)) {
            throw new PushDeliveryException('Web Push credentials are not configured.');
        }

        $subject = $config['subject'] ?? null;
        $publicKey = $config['public_key'] ?? null;
        $privateKey = $config['private_key'] ?? null;

        if (! is_string($subject) || $subject === '' || ! is_string($publicKey) || $publicKey === '' || ! is_string($privateKey) || $privateKey === '') {
            throw new PushDeliveryException('Web Push VAPID credentials are not configured.');
        }

        try {
            $webPush = new WebPush(
                auth: [
                    'VAPID' => [
                        'subject' => $subject,
                        'publicKey' => $publicKey,
                        'privateKey' => $privateKey,
                    ],
                ],
                defaultOptions: [
                    'TTL' => 300,
                    'urgency' => 'high',
                    'topic' => $payload['tag'],
                ],
                client: GuzzleAdapter::createWithConfig([
                    'allow_redirects' => false,
                    'connect_timeout' => (float) ($config['connect_timeout'] ?? 3),
                    'timeout' => (float) ($config['timeout'] ?? 10),
                ]),
                logger: $this->logger,
            );
            $webPush->setReuseVAPIDHeaders(true);

            $report = $webPush->sendOneNotification(
                $subscription->toWebPushSubscription(),
                json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            );

            if ($report->isSuccess()) {
                return PushDeliveryResult::sent();
            }

            if ($report->isSubscriptionExpired()) {
                return PushDeliveryResult::expired();
            }

            throw new PushDeliveryException($report->getReason());
        } catch (PushDeliveryException $exception) {
            $this->logger->warning('Web Push delivery failed.', [
                'subscription_id' => $subscription->id,
                'reason' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->error('Web Push delivery threw an unexpected exception.', [
                'subscription_id' => $subscription->id,
                'exception' => $exception,
            ]);

            throw new PushDeliveryException('Web Push delivery failed.', previous: $exception);
        }
    }
}
