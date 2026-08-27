<?php

namespace App\Notifications\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class UpsertPushSubscriptionData extends Data
{
    public function __construct(
        #[Url, Regex('/^https:\/\//'), Max(2048)]
        public readonly string $endpoint,

        #[Min(32), Max(512)]
        public readonly string $publicKey,

        #[Min(8), Max(256)]
        public readonly string $authToken,

        #[In('aes128gcm', 'aesgcm')]
        public readonly string $contentEncoding = 'aes128gcm',
    ) {}
}
