<?php

namespace App\Notifications\Models;

use App\Users\Models\User;
use Database\Factories\Notifications\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Minishlink\WebPush\Subscription;
use Override;

class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    #[Override]
    protected $fillable = [
        'user_id',
        'endpoint_hash',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
    ];

    #[Override]
    protected $hidden = [
        'endpoint_hash',
        'endpoint',
        'public_key',
        'auth_token',
    ];

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return [
            'endpoint' => 'encrypted',
            'public_key' => 'encrypted',
            'auth_token' => 'encrypted',
        ];
    }

    #[Override]
    protected static function booted(): void
    {
        static::saving(function (PushSubscription $subscription): void {
            $subscription->endpoint_hash = self::endpointHash($subscription->endpoint);
        });
    }

    public static function endpointHash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public static function isAllowedEndpoint(string $endpoint): bool
    {
        $host = parse_url($endpoint, PHP_URL_HOST);
        if (! is_string($host)) {
            return false;
        }

        $host = strtolower($host);

        return in_array($host, ['fcm.googleapis.com', 'notify.windows.com', 'push.services.mozilla.com', 'web.push.apple.com'], true)
            || str_ends_with($host, '.push.services.mozilla.com')
            || str_ends_with($host, '.notify.windows.com');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toWebPushSubscription(): Subscription
    {
        return Subscription::create([
            'endpoint' => $this->endpoint,
            'keys' => [
                'p256dh' => $this->public_key,
                'auth' => $this->auth_token,
            ],
            'contentEncoding' => $this->content_encoding,
        ]);
    }

    protected static function newFactory(): PushSubscriptionFactory
    {
        return PushSubscriptionFactory::new();
    }
}
