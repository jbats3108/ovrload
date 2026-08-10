<?php

namespace Tests\Feature\Exercises\Http\Controllers;

use App\Exercises\Models\Exercise;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class DeleteExerciseControllerTest extends TestCase
{
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(false);
    }

    #[Test]
    public function it_rejects_requests_from_non_admins(): void
    {
        // Given
        $exercise = Exercise::factory()->create();

        $route = route('exercises.delete', ['exercise' => $exercise->id]);

        // When
        $response = $this->actingAs($this->user)->delete($route);

        // Then
        $response->assertForbidden();

    }

    #[Test]
    public function it_deletes_an_exercise(): void
    {
        // Given
        $exercise = Exercise::factory()->create(['user_id' => null]);

        $route = route('exercises.delete', ['exercise' => $exercise->id]);

        // When
        $response = $this->actingAs($this->adminUser)->delete($route);

        // Then
        $response->assertRedirect(route('admin.exercises'));
        $this->assertSoftDeleted(Exercise::class, ['id' => $exercise->id]);
    }

    #[Test]
    public function it_rejects_deleting_a_custom_user_exercise_as_admin(): void
    {
        // Given
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $route = route('exercises.delete', ['exercise' => $exercise->id]);

        // When
        $response = $this->actingAs($this->adminUser)->delete($route);

        // Then
        $response->assertForbidden();
        $this->assertDatabaseHas(Exercise::class, [
            'id' => $exercise->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function the_owner_can_delete_their_custom_exercise(): void
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->from(route('dashboard'))
            ->actingAs($this->user)
            ->delete(route('exercises.delete', ['exercise' => $exercise->id]));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Custom exercise deleted.');
        $this->assertSoftDeleted(Exercise::class, ['id' => $exercise->id]);
    }

    #[Test]
    public function another_user_cannot_delete_a_custom_exercise(): void
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $other = User::factory()->create()->assignRole('user');

        $this->actingAs($other)
            ->delete(route('exercises.delete', ['exercise' => $exercise->id]))
            ->assertForbidden();

        $this->assertDatabaseHas(Exercise::class, [
            'id' => $exercise->id,
            'deleted_at' => null,
        ]);
    }
}
