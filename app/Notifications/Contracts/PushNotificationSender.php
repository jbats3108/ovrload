<?php

namespace App\Notifications\Contracts;

use App\Notifications\Models\PushSubscription;
use App\Notifications\ValueObjects\PushDeliveryResult;

interface PushNotificationSender
{
    /**
     * @param  array{title: string, body: string, url: string, tag: string}  $payload
     */
    public function send(PushSubscription $subscription, array $payload): PushDeliveryResult;
}
