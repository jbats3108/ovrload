<?php

namespace Tests\Feature\Exercises\Http\Controllers;

use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class StoreCustomExerciseControllerTest extends TestCase
{
    use RefreshDatabase;
    use UserHelper;

    private MuscleGroup $validMuscleGroup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(false);

        $this->validMuscleGroup = MuscleGroup::factory()->create();
    }

    #[Test]
    public function guests_cannot_create_custom_exercises(): void
    {
        $this->postJson(route('exercises.custom.store'), [
            'name' => 'Cable Pull-Through',
            'primary_muscle_group' => $this->validMuscleGroup->getSlug(),
        ])->assertUnauthorized();
    }

    #[Test]
    public function it_creates_a_custom_exercise_owned_by_the_user(): void
    {
        $secondary = MuscleGroup::factory()->create();

        $response = $this->makeRequest([
            'name' => 'Cable Pull-Through',
            'primary_muscle_group' => $this->validMuscleGroup->getSlug(),
            'secondary_muscle_group' => $secondary->getSlug(),
            'equipment' => 'cable',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'Cable Pull-Through');
        $response->assertJsonPath('is_custom', true);
        $response->assertJsonPath('primary_muscle_group', $this->validMuscleGroup->getName());

        $this->assertDatabaseHas(Exercise::class, [
            'name' => 'Cable Pull-Through',
            'slug' => 'cable-pull-through',
            'user_id' => $this->user->id,
            'equipment' => 'cable',
            'primary_muscle_group_id' => $this->validMuscleGroup->id,
            'secondary_muscle_group_id' => $secondary->id,
        ]);
    }

    #[Test]
    public function it_does_not_require_admin(): void
    {
        $response = $this->makeRequest([
            'name' => 'Landmine Twist',
            'primary_muscle_group' => $this->validMuscleGroup->getSlug(),
        ]);

        $response->assertCreated();
        /** @var Exercise $exercise */
        $exercise = Exercise::query()->findOrFail($response->json('id'));
        $this->assertTrue($exercise->isCustom());
    }

    #[Test]
    public function another_user_does_not_see_the_custom_in_for_user_scope(): void
    {
        $created = $this->makeRequest([
            'name' => 'Private Lift',
            'primary_muscle_group' => $this->validMuscleGroup->getSlug(),
        ]);

        $other = User::factory()->create()->assignRole('user');

        $this->assertFalse(
            Exercise::query()
                ->forUser($other)
                ->whereKey($created->json('id'))
                ->exists(),
        );
        $this->assertTrue(
            Exercise::query()
                ->forUser($this->user)
                ->whereKey($created->json('id'))
                ->exists(),
        );
    }

    #[Test]
    public function it_rejects_invalid_muscle_groups(): void
    {
        $this->makeRequest([
            'name' => 'Test',
            'primary_muscle_group' => 'missing',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('primary_muscle_group');
    }

    #[Test]
    public function it_requires_secondary_to_differ_from_primary(): void
    {
        $this->makeRequest([
            'name' => 'Test',
            'primary_muscle_group' => $this->validMuscleGroup->getSlug(),
            'secondary_muscle_group' => $this->validMuscleGroup->getSlug(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('secondary_muscle_group');
    }

    /**
     * @param  array{
     *     name: string,
     *     primary_muscle_group: string,
     *     secondary_muscle_group?: string|null,
     *     equipment?: string|null,
     * }  $payload
     * @return TestResponse<Response>
     */
    private function makeRequest(array $payload): TestResponse
    {
        return $this->actingAs($this->user)->postJson(route('exercises.custom.store'), $payload);
    }
}
