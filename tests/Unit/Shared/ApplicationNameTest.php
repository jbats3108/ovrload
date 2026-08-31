<?php

namespace Tests\Unit\Shared;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplicationNameTest extends TestCase
{
    #[Test]
    public function the_product_name_is_uppercase_ovrload_regardless_of_app_name_env(): void
    {
        $this->assertSame('OVRLOAD', config('app.name'));
        $this->assertSame('OVRLOAD', config('mail.from.name'));
    }
}
