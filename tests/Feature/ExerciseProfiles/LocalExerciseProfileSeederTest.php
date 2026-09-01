<?php

namespace Tests\Feature\ExerciseProfiles;

use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Users\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalBlankSlateUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalExerciseProfileSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function local_seed_reuses_presets_with_two_custom_profiles_for_demo_variety(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', 'user1@test.com')->firstOrFail();
        $strength = ExerciseProfile::query()->where('slug', 'preset-strength')->firstOrFail();
        $customProfiles = $user->exerciseProfiles()->get();
        $routineIds = $user->routines()->pluck('id');
        $blockIds = RoutineBlock::query()->whereIn('routine_id', $routineIds)->pluck('id');
        $routineExercises = RoutineBlockExercise::query()
            ->whereIn('routine_block_id', $blockIds)
            ->with('exerciseProfile')
            ->get();

        $this->assertSame(
            2,
            $user->exerciseProfiles()->count(),
        );
        $this->assertSame(
            3,
            ExerciseProfile::query()->where('kind', ExerciseProfileKind::Custom)->count(),
        );
        $this->assertSame(['Accessory Volume', 'Power Builder'], $customProfiles->sortBy('name')->pluck('name')->values()->all());
        $this->assertSame($strength->id, $user->default_exercise_profile_id);
        $this->assertCount(4, $user->routines);
        $this->assertNotEmpty($routineExercises);
        $this->assertTrue($routineExercises->every(
            static fn (RoutineBlockExercise $exercise): bool => $exercise->exerciseProfile !== null,
        ));
    }

    #[Test]
    public function user2_is_a_blank_slate_fixture_for_profile_testing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::query()->where('email', LocalBlankSlateUserSeeder::EMAIL)->firstOrFail();

        $this->assertNull($user->default_exercise_profile_id);
        $this->assertCount(0, $user->routines);
        $this->assertSame(
            ['Deletable Test'],
            $user->exerciseProfiles()->orderBy('name')->pluck('name')->all(),
        );
    }
}
