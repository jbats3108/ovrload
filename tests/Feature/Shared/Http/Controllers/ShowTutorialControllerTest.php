<?php

namespace Tests\Feature\Shared\Http\Controllers;

use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShowTutorialControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_can_view_the_tutorial_page(): void
    {
        $this->get(route('tutorial'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Tutorial'));
    }

    #[Test]
    public function authenticated_users_can_view_the_tutorial_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tutorial'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page->component('Tutorial'));
    }
}
