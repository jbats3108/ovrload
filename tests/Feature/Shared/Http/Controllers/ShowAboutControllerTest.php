<?php

namespace Tests\Feature\Shared\Http\Controllers;

use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowAboutControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_can_view_the_about_page(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('About'));
    }

    #[Test]
    public function authenticated_users_can_view_the_about_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('about'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('About'));
    }
}
