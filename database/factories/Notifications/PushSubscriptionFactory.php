<?php

namespace Database\Factories\Notifications;

use App\Notifications\Models\PushSubscription;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'endpoint' => 'https://web.push.apple.com/3/device/'.fake()->uuid(),
            'public_key' => str_repeat('A', 87),
            'auth_token' => str_repeat('B', 22),
            'content_encoding' => 'aes128gcm',
        ];
    }
}
