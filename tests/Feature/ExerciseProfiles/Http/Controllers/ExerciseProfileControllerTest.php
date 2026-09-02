<?php

namespace Tests\Feature\ExerciseProfiles\Http\Controllers;

use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Shared\Enums\SetGroupType;
use App\Shared\Enums\WarmUpWeightMode;
use App\Users\Models\User;
use Database\Seeders\ExerciseProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExerciseProfileSeeder::class);
    }

    #[Test]
    public function a_user_can_view_their_profiles_and_published_presets(): void
    {
        $user = User::factory()->create();
        $custom = ExerciseProfile::factory()->forUser($user)->create(['name' => 'My Push']);
        $user->forceFill(['default_exercise_profile_id' => $custom->id])->save();
        $user->refresh();

        $this->actingAs($user)
            ->get(route('training.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Training')
                ->where('exercise_profiles.default_profile_id', $custom->id)
                ->where('exercise_profiles.profiles.0.id', $custom->id)
                ->where('exercise_profiles.profiles.0.is_default', true)
                ->where('exercise_profiles.profiles.3.display_name', 'OVRLOAD Strength'));
    }

    #[Test]
    public function training_page_lists_routines_assigned_to_a_profile(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create(['name' => 'Push']);
        $routine = Routine::factory()->withUser($user)->create([
            'name' => 'Upper A',
            'default_exercise_profile_id' => $profile->id,
        ]);
        $user->refresh();

        $this->actingAs($user)
            ->get(route('training.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('exercise_profiles.profiles', function (array $profiles) use ($profile, $routine): bool {
                    $match = collect($profiles)->firstWhere('id', $profile->id);

                    return $match !== null
                        && $match['assigned_routines'] === [
                            ['name' => 'Upper A', 'slug' => $routine->slug],
                        ];
                }));
    }

    #[Test]
    public function a_user_can_create_a_custom_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('exercise-profiles.store'), [
                'name' => 'Heavy Pull',
                'target_reps' => 6,
                'floor_override' => null,
                'working_rest_seconds' => 180,
                'warm_up_steps' => [
                    ['percent' => 50, 'reps' => 5],
                    ['percent' => 75, 'reps' => 3],
                ],
            ])
            ->assertRedirect(route('training.edit'));

        $profile = $user->fresh()->exerciseProfiles()->firstOrFail();
        $this->assertSame('Heavy Pull', $profile->name);
        $this->assertSame('heavy-pull', $profile->slug);
        $this->assertSame(4, $profile->resolvedFloor());
    }

    #[Test]
    public function a_custom_profile_can_store_fixed_weight_warm_up_steps(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('exercise-profiles.store'), [
                'name' => 'Deadlift Ladder',
                'target_reps' => 5,
                'floor_override' => null,
                'working_rest_seconds' => 180,
                'warm_up_steps' => [
                    ['mode' => 'fixed', 'weight_kg' => 60, 'reps' => 5],
                ],
            ])
            ->assertRedirect(route('training.edit'));

        $profile = $user->fresh()->exerciseProfiles()->firstOrFail();
        $this->assertEquals(
            [
                ['mode' => 'fixed', 'weight_kg' => 60, 'reps' => 5],
            ],
            $profile->warmUpStepList()
        );
    }

    #[Test]
    public function a_custom_profile_can_explicitly_have_no_warm_up_steps(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('exercise-profiles.store'), [
                ...$this->profilePayload('No Warm-up'),
                'warm_up_steps' => [],
            ])
            ->assertRedirect(route('training.edit'));

        $this->assertSame([], $user->fresh()->exerciseProfiles()->firstOrFail()->warm_up_steps);
    }

    #[Test]
    public function a_custom_profile_can_be_created_as_json_for_inline_routine_setup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('exercise-profiles.store'), $this->profilePayload('Inline Profile'))
            ->assertCreated()
            ->assertJsonPath('name', 'Inline Profile')
            ->assertJsonPath('display_name', 'Inline Profile');
    }

    #[Test]
    public function custom_profile_names_are_case_insensitively_unique_and_cannot_use_the_brand_prefix(): void
    {
        $user = User::factory()->create();
        ExerciseProfile::factory()->forUser($user)->create(['name' => 'Strength']);

        $this->actingAs($user)
            ->post(route('exercise-profiles.store'), $this->profilePayload('strength'))
            ->assertSessionHasErrors('name');

        $this->actingAs($user)
            ->post(route('exercise-profiles.store'), $this->profilePayload('OVRLOAD Power'))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $user->fresh()->exerciseProfiles()->count());
    }

    #[Test]
    public function a_user_can_set_a_published_preset_as_their_default(): void
    {
        $user = User::factory()->create();
        $preset = ExerciseProfile::query()->where('slug', 'preset-strength')->firstOrFail();

        $this->actingAs($user)
            ->post(route('exercise-profiles.default', $preset))
            ->assertRedirect(route('training.edit'));

        $this->assertSame($preset->id, $user->fresh()->default_exercise_profile_id);
    }

    #[Test]
    public function a_user_cannot_set_another_users_profile_as_their_default(): void
    {
        $user = User::factory()->create();
        $otherProfile = ExerciseProfile::factory()->create();

        $this->actingAs($user)
            ->post(route('exercise-profiles.default', $otherProfile))
            ->assertForbidden();
    }

    #[Test]
    public function a_user_cannot_archive_their_current_default_profile(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create();
        $user->forceFill(['default_exercise_profile_id' => $profile->id])->save();

        $this->actingAs($user)
            ->post(route('exercise-profiles.archive', $profile))
            ->assertSessionHasErrors('profile');

        $this->assertSame(ExerciseProfileStatus::Published, $profile->fresh()->status);
    }

    #[Test]
    public function a_user_can_archive_and_restore_a_non_default_profile(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create();
        $user->refresh();

        $this->actingAs($user)
            ->post(route('exercise-profiles.archive', $profile))
            ->assertRedirect(route('training.edit'));
        $this->assertSame(ExerciseProfileStatus::Archived, $profile->fresh()->status);

        $this->actingAs($user)
            ->post(route('exercise-profiles.restore', $profile))
            ->assertRedirect(route('training.edit'));
        $this->assertSame(ExerciseProfileStatus::Published, $profile->fresh()->status);
    }

    #[Test]
    public function a_profile_in_use_cannot_be_permanently_deleted(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create();
        $preset = ExerciseProfile::query()->where('slug', 'preset-strength')->firstOrFail();
        $routine = Routine::factory()->withUser($user)->create([
            'default_exercise_profile_id' => $profile->id,
        ]);

        $this->actingAs($user)
            ->delete(route('exercise-profiles.delete', $profile))
            ->assertSessionHasErrors('profile');

        $this->assertNotNull($routine->fresh());
        $this->assertNotNull($profile->fresh());

        $routine->forceFill(['default_exercise_profile_id' => $preset->id])->save();

        $this->actingAs($user)
            ->delete(route('exercise-profiles.delete', $profile))
            ->assertRedirect(route('training.edit'));
        $this->assertNull($profile->fresh());
    }

    #[Test]
    public function an_unused_custom_profile_can_be_permanently_deleted(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create();

        $this->actingAs($user)
            ->delete(route('exercise-profiles.delete', $profile))
            ->assertRedirect(route('training.edit'));

        $this->assertNull($profile->fresh());
    }

    #[Test]
    public function a_profile_linked_to_routines_cannot_be_archived(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create();
        Routine::factory()->withUser($user)->create([
            'default_exercise_profile_id' => $profile->id,
        ]);

        $this->actingAs($user)
            ->post(route('exercise-profiles.archive', $profile))
            ->assertSessionHasErrors('profile');

        $this->assertSame(ExerciseProfileStatus::Published, $profile->fresh()->status);
    }

    #[Test]
    public function training_page_reports_stale_assignments_after_a_profile_is_edited(): void
    {
        $user = User::factory()->create();
        $user->refresh();
        $profile = ExerciseProfile::factory()->forUser($user)->create([
            'target_reps' => 6,
            'working_rest_seconds' => 120,
            'warm_up_steps' => [['percent' => 50, 'reps' => 5]],
        ]);
        $routine = Routine::factory()->withUser($user)->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'shared_exercise_profile_id' => $profile->id,
            'shared_profile_fingerprint' => $profile->recipe()->sharedFingerprint(),
        ]);
        $exercise = Exercise::factory()->create();
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => $exercise->id,
            'position' => 1,
            'working_weight_g' => 60000,
            'prescribed_reps' => 6,
            'exercise_profile_id' => $profile->id,
            'exercise_profile_fingerprint' => $profile->recipe()->fingerprint(),
        ]);

        $this->actingAs($user)
            ->get(route('training.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('exercise_profiles.profiles', function (array $profiles) use ($profile): bool {
                    $match = collect($profiles)->firstWhere('id', $profile->id);

                    return $match !== null && $match['stale_assignment_count'] === 0;
                }));

        $profile->update([
            'target_reps' => 8,
            'working_rest_seconds' => 180,
            'recipe_fingerprint' => $profile->recipe()->fingerprint(),
        ]);

        $this->actingAs($user)
            ->get(route('training.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('exercise_profiles.profiles', function (array $profiles) use ($profile): bool {
                    $match = collect($profiles)->firstWhere('id', $profile->id);

                    return $match !== null && $match['stale_assignment_count'] === 2;
                }));
    }

    #[Test]
    public function a_user_can_sync_an_edited_profile_to_assigned_blocks(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create([
            'target_reps' => 6,
            'floor_override' => null,
            'working_rest_seconds' => 120,
            'warm_up_steps' => [['percent' => 50, 'reps' => 5]],
        ]);
        $routine = Routine::factory()->withUser($user)->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'shared_exercise_profile_id' => $profile->id,
            'shared_profile_fingerprint' => $profile->recipe()->sharedFingerprint(),
        ]);
        $exercise = Exercise::factory()->create();
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => $exercise->id,
            'position' => 1,
            'working_weight_g' => 60000,
            'prescribed_reps' => 6,
            'exercise_profile_id' => $profile->id,
            'exercise_profile_fingerprint' => $profile->recipe()->fingerprint(),
        ]);
        $working = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 3,
            'rest_seconds' => 120,
        ]);
        $warmUp = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 1,
            'rest_seconds' => 60,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUp->id,
            'position' => 1,
            'percent_of_working' => 50,
            'reps' => 5,
        ]);

        $profile->update([
            'target_reps' => 8,
            'working_rest_seconds' => 180,
            'warm_up_steps' => [['percent' => 75, 'reps' => 3]],
            'recipe_fingerprint' => $profile->recipe()->fingerprint(),
        ]);

        $this->actingAs($user)
            ->post(route('exercise-profiles.sync', $profile))
            ->assertRedirect(route('training.edit'));

        $updatedBlock = $block->fresh(['blockExercises', 'setGroups.warmUpSteps']);
        $updatedExercise = $updatedBlock->blockExercises->firstOrFail();
        $this->assertSame(8, $updatedExercise->prescribed_reps);
        $this->assertSame(180, $updatedBlock->setGroups->firstWhere('type', SetGroupType::Working)->rest_seconds);
        $this->assertSame(75, $updatedBlock->setGroups->firstWhere('type', SetGroupType::WarmUp)->warmUpSteps->first()->percent_of_working);
    }

    #[Test]
    public function syncing_a_profile_copies_fixed_warm_up_weight_onto_the_block(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create([
            'target_reps' => 6,
            'floor_override' => null,
            'working_rest_seconds' => 120,
            'warm_up_steps' => [['percent' => 50, 'reps' => 5]],
        ]);
        $routine = Routine::factory()->withUser($user)->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'shared_exercise_profile_id' => $profile->id,
            'shared_profile_fingerprint' => $profile->recipe()->sharedFingerprint(),
        ]);
        $exercise = Exercise::factory()->create();
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => $exercise->id,
            'position' => 1,
            'working_weight_g' => 60000,
            'prescribed_reps' => 6,
            'exercise_profile_id' => $profile->id,
            'exercise_profile_fingerprint' => $profile->recipe()->fingerprint(),
        ]);
        RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::Working,
            'set_count' => 3,
            'rest_seconds' => 120,
        ]);
        $warmUp = RoutineSetGroup::create([
            'routine_block_id' => $block->id,
            'type' => SetGroupType::WarmUp,
            'set_count' => 1,
            'rest_seconds' => 60,
        ]);
        RoutineWarmUpStep::create([
            'routine_set_group_id' => $warmUp->id,
            'position' => 1,
            'percent_of_working' => 50,
            'reps' => 5,
        ]);

        $profile->update([
            'warm_up_steps' => [['mode' => 'fixed', 'weight_kg' => 60, 'reps' => 5]],
            'recipe_fingerprint' => $profile->recipe()->fingerprint(),
        ]);

        $this->actingAs($user)
            ->post(route('exercise-profiles.sync', $profile))
            ->assertRedirect(route('training.edit'));

        $step = $block->fresh(['setGroups.warmUpSteps'])
            ->setGroups
            ->firstWhere('type', SetGroupType::WarmUp)
            ->warmUpSteps
            ->first();
        $this->assertSame(WarmUpWeightMode::Fixed, $step->weight_mode);
        $this->assertNull($step->percent_of_working);
        $this->assertSame(60_000, $step->weight_g);
        $this->assertSame(5, $step->reps);
    }

    /**
     * @return array{
     *     name: string,
     *     target_reps: int,
     *     floor_override: int|null,
     *     working_rest_seconds: int,
     *     warm_up_steps: list<array{percent: int, reps: int}>
     * }
     */
    private function profilePayload(string $name): array
    {
        return [
            'name' => $name,
            'target_reps' => 6,
            'floor_override' => null,
            'working_rest_seconds' => 120,
            'warm_up_steps' => [
                ['percent' => 50, 'reps' => 5],
            ],
        ];
    }
}
