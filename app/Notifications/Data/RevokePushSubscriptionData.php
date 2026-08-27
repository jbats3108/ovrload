<?php

namespace App\Notifications\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\Url;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class RevokePushSubscriptionData extends Data
{
    public function __construct(
        #[Url, Regex('/^https:\/\//'), Max(2048)]
        public readonly string $endpoint,
    ) {}
}
