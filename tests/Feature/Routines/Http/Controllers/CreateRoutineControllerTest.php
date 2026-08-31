<?php

namespace Tests\Feature\Routines\Http\Controllers;

use Database\Seeders\ExerciseProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class CreateRoutineControllerTest extends TestCase
{
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(false);
        $this->seed(ExerciseProfileSeeder::class);
    }

    #[Test]
    public function it_renders_the_create_page_for_authenticated_users(): void
    {
        $response = $this->actingAs($this->user)->get(route('routines.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('routines/Create')
            ->has('exercise_profiles')
            ->where('default_exercise_profile_id', null));
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('routines.create'))->assertRedirect(route('login'));
    }
}
