<?php

namespace Tests\Unit\ExerciseProfiles;

use App\ExerciseProfiles\Data\SaveExerciseProfileData;
use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\ExerciseProfiles\Services\ExerciseProfileService;
use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Users\Models\User;
use Database\Seeders\ExerciseProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class ExerciseProfileServiceTest extends TestCase
{
    use RefreshDatabase;
    use UserHelper;

    private ExerciseProfileService $profiles;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExerciseProfileSeeder::class);
        $this->profiles = app(ExerciseProfileService::class);
    }

    #[Test]
    public function it_reports_zero_stale_assignments_when_fingerprints_match(): void
    {
        $user = User::factory()->create();
        $profile = $this->assignedProfile($user);

        $count = $this->staleCountFor($user, $profile);

        $this->assertSame(0, $count);
    }

    #[Test]
    public function it_counts_only_the_exercise_assignment_when_target_reps_change(): void
    {
        $user = User::factory()->create();
        $profile = $this->assignedProfile($user);

        $profile->update([
            'target_reps' => 8,
            'recipe_fingerprint' => $profile->recipe()->fingerprint(),
        ]);

        $this->assertSame(1, $this->staleCountFor($user, $profile->fresh()));
    }

    #[Test]
    public function it_counts_both_assignments_when_shared_recipe_fields_change(): void
    {
        $user = User::factory()->create();
        $profile = $this->assignedProfile($user);

        $profile->update([
            'working_rest_seconds' => 180,
            'recipe_fingerprint' => $profile->recipe()->fingerprint(),
        ]);

        $this->assertSame(2, $this->staleCountFor($user, $profile->fresh()));
    }

    #[Test]
    public function sync_updates_linked_assignments_but_skips_custom_exercises(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create([
            'target_reps' => 6,
            'working_rest_seconds' => 120,
            'warm_up_steps' => [['percent' => 50, 'reps' => 5]],
        ]);
        $other = ExerciseProfile::factory()->forUser($user)->create(['name' => 'Other']);
        $routine = Routine::factory()->withUser($user)->create();
        $linked = $this->createAssignedBlock($routine, $profile);
        $custom = $this->createAssignedBlock($routine, $profile, customExercise: true);
        $foreign = $this->createAssignedBlock($routine, $other);

        $profile->update([
            'target_reps' => 8,
            'recipe_fingerprint' => $profile->recipe()->fingerprint(),
        ]);

        $updated = $this->profiles->syncProfile($user, $profile->fresh());

        $this->assertSame(2, $updated);
        $this->assertSame(8, $linked->fresh()->blockExercises->firstOrFail()->prescribed_reps);
        $this->assertNull($custom->fresh()->blockExercises->firstOrFail()->exercise_profile_id);
        $this->assertSame(6, $custom->fresh()->blockExercises->firstOrFail()->prescribed_reps);
        $this->assertSame(6, $foreign->fresh()->blockExercises->firstOrFail()->prescribed_reps);
    }

    #[Test]
    public function sync_uses_the_exercise_fingerprint_for_superset_assignments(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create([
            'target_reps' => 6,
            'working_rest_seconds' => 120,
            'warm_up_steps' => [['percent' => 50, 'reps' => 5]],
        ]);
        $routine = Routine::factory()->withUser($user)->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'is_superset' => true,
            'shared_exercise_profile_id' => $profile->id,
            'shared_profile_fingerprint' => $profile->recipe()->sharedFingerprint(),
        ]);
        $first = Exercise::factory()->create();
        $second = Exercise::factory()->create();
        foreach ([$first, $second] as $index => $exercise) {
            RoutineBlockExercise::create([
                'routine_block_id' => $block->id,
                'exercise_id' => $exercise->id,
                'position' => $index + 1,
                'working_weight_g' => 60000,
                'prescribed_reps' => 6,
                'exercise_profile_id' => $profile->id,
                'exercise_profile_fingerprint' => $profile->recipe()->fingerprint(),
            ]);
        }

        $profile->update([
            'target_reps' => 8,
            'recipe_fingerprint' => $profile->recipe()->fingerprint(),
        ]);

        $this->profiles->syncProfile($user, $profile->fresh());

        $rows = $block->fresh()->blockExercises()->orderBy('position')->get();
        $this->assertSame(8, $rows[0]->prescribed_reps);
        $this->assertSame($profile->fresh()->recipe()->exerciseFingerprint(), $rows[0]->exercise_profile_fingerprint);
        $this->assertSame($profile->fresh()->recipe()->exerciseFingerprint(), $rows[1]->exercise_profile_fingerprint);
    }

    #[Test]
    public function it_counts_only_the_exercise_assignment_on_mixed_shared_and_exercise_profiles(): void
    {
        $user = User::factory()->create();
        $shared = ExerciseProfile::factory()->forUser($user)->create(['name' => 'Shared Strength']);
        $exerciseProfile = ExerciseProfile::factory()->forUser($user)->create([
            'name' => 'Hypertrophy Lift',
            'target_reps' => 10,
            'working_rest_seconds' => 90,
            'warm_up_steps' => [['percent' => 50, 'reps' => 10]],
        ]);
        $routine = Routine::factory()->withUser($user)->create();
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'shared_exercise_profile_id' => $shared->id,
            'shared_profile_fingerprint' => $shared->recipe()->sharedFingerprint(),
        ]);
        $exercise = Exercise::factory()->create();
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => $exercise->id,
            'position' => 1,
            'working_weight_g' => 60000,
            'prescribed_reps' => 10,
            'exercise_profile_id' => $exerciseProfile->id,
            'exercise_profile_fingerprint' => $exerciseProfile->recipe()->exerciseFingerprint(),
        ]);

        $exerciseProfile->update([
            'target_reps' => 12,
            'recipe_fingerprint' => $exerciseProfile->recipe()->fingerprint(),
        ]);

        $page = $this->profiles->pageDataFor($user);
        $sharedMatch = collect($page->profiles->all())->firstWhere('id', $shared->id);
        $exerciseMatch = collect($page->profiles->all())->firstWhere('id', $exerciseProfile->id);

        $this->assertSame(0, $sharedMatch?->staleAssignmentCount);
        $this->assertSame(1, $exerciseMatch?->staleAssignmentCount);
    }

    #[Test]
    public function options_for_routine_editor_includes_archived_profiles_still_referenced_by_the_routine(): void
    {
        $user = User::factory()->create();
        $archived = ExerciseProfile::factory()->forUser($user)->archived()->create(['name' => 'Old Push']);
        $routine = Routine::factory()->withUser($user)->create([
            'default_exercise_profile_id' => ExerciseProfile::query()->where('slug', 'preset-strength')->value('id'),
        ]);
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => 1,
            'shared_exercise_profile_id' => $archived->id,
            'shared_profile_fingerprint' => $archived->recipe()->sharedFingerprint(),
        ]);
        $exercise = Exercise::factory()->create();
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => $exercise->id,
            'position' => 1,
            'working_weight_g' => 60000,
            'prescribed_reps' => 6,
            'exercise_profile_id' => $archived->id,
            'exercise_profile_fingerprint' => $archived->recipe()->fingerprint(),
        ]);

        $options = $this->profiles->optionsForRoutineEditor($user, $routine->fresh(['blocks.blockExercises']));

        $this->assertTrue(
            collect($options)->contains(fn ($option): bool => $option->id === $archived->id && $option->status === ExerciseProfileStatus::Archived->value),
        );
        $this->assertFalse(
            collect($options)->contains(fn ($option): bool => $option->kind === ExerciseProfileKind::Custom->value
                && $option->status === ExerciseProfileStatus::Archived->value
                && $option->id !== $archived->id),
        );
    }

    #[Test]
    public function admins_can_publish_a_preset_draft_through_the_service(): void
    {
        $this->seedUsers(false);
        $admin = $this->adminUser;
        $draft = $this->profiles->createPreset($admin, SaveExerciseProfileData::from([
            'name' => 'Power',
            'target_reps' => 5,
            'floor_override' => null,
            'working_rest_seconds' => 180,
            'warm_up_steps' => [
                ['percent' => 50, 'reps' => 5],
            ],
        ]));

        $published = $this->profiles->publishPreset($admin, $draft);

        $this->assertSame(ExerciseProfileStatus::Published, $published->status);
        $this->assertSame('preset-power', $published->slug);
        $this->assertNotNull($published->published_at);
    }

    #[Test]
    public function admins_cannot_publish_a_preset_with_a_duplicate_recipe_through_the_service(): void
    {
        $this->seedUsers(false);
        $strength = ExerciseProfile::query()->where('slug', 'preset-strength')->firstOrFail();
        $draft = ExerciseProfile::factory()->preset()->draft()->withRecipe($strength->recipe())->create([
            'name' => 'Heavy Strength',
            'slug' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A published preset already uses those Profile Details.');

        $this->profiles->publishPreset($this->adminUser, $draft);
    }

    #[Test]
    public function routine_reference_count_counts_distinct_routines_not_assignments(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create();
        $routine = Routine::factory()->withUser($user)->create([
            'default_exercise_profile_id' => $profile->id,
        ]);
        $this->createAssignedBlock($routine, $profile);
        $this->createAssignedBlock($routine, $profile);

        $this->assertSame(1, $this->referenceCountFor($user, $profile));
    }

    #[Test]
    public function routine_reference_count_ignores_soft_deleted_routines(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create();
        $routine = Routine::factory()->withUser($user)->create([
            'default_exercise_profile_id' => $profile->id,
        ]);
        $this->createAssignedBlock($routine, $profile);

        $routine->delete();

        $this->assertSame(0, $this->referenceCountFor($user, $profile));
    }

    #[Test]
    public function delete_allows_profile_after_routine_is_soft_deleted(): void
    {
        $user = User::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($user)->create();
        $routine = Routine::factory()->withUser($user)->create();
        $this->createAssignedBlock($routine, $profile);

        $routine->delete();

        $this->profiles->delete($user, $profile);

        $this->assertDatabaseMissing('exercise_profiles', ['id' => $profile->id]);
    }

    private function assignedProfile(User $user): ExerciseProfile
    {
        $profile = ExerciseProfile::factory()->forUser($user)->create([
            'target_reps' => 6,
            'working_rest_seconds' => 120,
            'warm_up_steps' => [['percent' => 50, 'reps' => 5]],
        ]);
        $routine = Routine::factory()->withUser($user)->create();
        $this->createAssignedBlock($routine, $profile);

        return $profile;
    }

    private function staleCountFor(User $user, ExerciseProfile $profile): int
    {
        $page = $this->profiles->pageDataFor($user);
        $match = collect($page->profiles->all())->firstWhere('id', $profile->id);

        $this->assertNotNull($match);

        return $match->staleAssignmentCount;
    }

    private function referenceCountFor(User $user, ExerciseProfile $profile): int
    {
        $page = $this->profiles->pageDataFor($user);
        $match = collect($page->profiles->all())->firstWhere('id', $profile->id);

        $this->assertNotNull($match);

        return $match->referenceCount;
    }

    private function createAssignedBlock(
        Routine $routine,
        ExerciseProfile $profile,
        bool $customExercise = false,
    ): RoutineBlock {
        $block = RoutineBlock::create([
            'routine_id' => $routine->id,
            'position' => $routine->blocks()->count() + 1,
            'shared_exercise_profile_id' => $customExercise ? null : $profile->id,
            'shared_profile_fingerprint' => $customExercise ? null : $profile->recipe()->sharedFingerprint(),
        ]);
        $exercise = Exercise::factory()->create();
        RoutineBlockExercise::create([
            'routine_block_id' => $block->id,
            'exercise_id' => $exercise->id,
            'position' => 1,
            'working_weight_g' => 60000,
            'prescribed_reps' => 6,
            'exercise_profile_id' => $customExercise ? null : $profile->id,
            'exercise_profile_fingerprint' => $customExercise ? null : $profile->recipe()->fingerprint(),
        ]);

        return $block;
    }
}
