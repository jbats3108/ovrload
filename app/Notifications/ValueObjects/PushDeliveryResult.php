<?php

namespace App\Notifications\ValueObjects;

final readonly class PushDeliveryResult
{
    public function __construct(
        public bool $sent,
        public bool $expired = false,
    ) {}

    public static function sent(): self
    {
        return new self(sent: true);
    }

    public static function expired(): self
    {
        return new self(sent: false, expired: true);
    }
}
