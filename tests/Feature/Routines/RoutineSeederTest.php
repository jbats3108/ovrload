<?php

namespace Tests\Feature\Routines;

use App\Exercises\Enums\ExerciseEquipment;
use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use App\Routines\Models\Routine;
use App\Shared\Enums\SetGroupType;
use App\Shared\Enums\WarmUpWeightMode;
use App\Users\Enums\WarmUpDefaultsScope;
use App\Users\Models\User;
use Database\Seeders\ExerciseProfileSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\RoutineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RoutineSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_seeds_demo_routines_with_mixed_permutations_for_user1(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ExerciseProfileSeeder::class);
        $this->seedCatalogExercises();

        $user = User::factory()->withRole('user')->create([
            'email' => 'user1@test.com',
        ]);

        $this->seed(RoutineSeeder::class);

        $user->refresh();
        $this->assertSame(WarmUpDefaultsScope::FirstBlock, $user->warm_up_defaults_scope);

        $routines = Routine::query()->where('user_id', $user->id)->get();
        $this->assertEqualsCanonicalizing(RoutineSeeder::DEMO_NAMES, $routines->pluck('name')->all());

        $barbell = Routine::query()->where('user_id', $user->id)->where('name', 'Barbell Strength')->firstOrFail();
        $barbell->load(['blocks.setGroups.warmUpSteps', 'blocks.blockExercises.exercise']);
        $this->assertCount(3, $barbell->blocks);
        $this->assertTrue($barbell->blocks[0]->has_setup_after_warm_up);
        $warmUpSteps = $barbell->blocks[0]->setGroups->firstWhere('type', SetGroupType::WarmUp)->warmUpSteps;
        $this->assertCount(4, $warmUpSteps);
        $this->assertSame(WarmUpWeightMode::Bar, $warmUpSteps->first()->weight_mode);
        $this->assertSame(10, $warmUpSteps->first()->reps);
        $this->assertCount(0, $barbell->blocks[1]->setGroups->firstWhere('type', SetGroupType::WarmUp)->warmUpSteps);
        $this->assertSame(ExerciseEquipment::Barbell, $barbell->blocks[0]->blockExercises[0]->exercise->equipment);

        $dumbbell = Routine::query()->where('user_id', $user->id)->where('name', 'Dumbbell Accessories')->firstOrFail();
        $dumbbell->load(['blocks.setGroups.warmUpSteps', 'blocks.blockExercises']);
        $this->assertCount(4, $dumbbell->blocks);
        foreach ($dumbbell->blocks as $block) {
            $this->assertGreaterThan(0, $block->setGroups->firstWhere('type', SetGroupType::WarmUp)->warmUpSteps->count());
        }
        $this->assertSame(28750, $dumbbell->blocks[3]->blockExercises[0]->working_weight_g);

        $superset = Routine::query()->where('user_id', $user->id)->where('name', 'Superset Pump')->firstOrFail();
        $superset->load(['blocks.setGroups.warmUpSteps']);
        $this->assertTrue($superset->blocks[0]->is_superset);
        $this->assertTrue($superset->blocks[1]->is_superset);
        $this->assertFalse($superset->blocks[2]->is_superset);
        $this->assertGreaterThan(0, $superset->blocks[0]->setGroups->firstWhere('type', SetGroupType::WarmUp)->warmUpSteps->count());
        $this->assertCount(0, $superset->blocks[1]->setGroups->firstWhere('type', SetGroupType::WarmUp)->warmUpSteps);
        $this->assertGreaterThan(0, $superset->blocks[2]->setGroups->firstWhere('type', SetGroupType::WarmUp)->warmUpSteps->count());

        $dropset = Routine::query()->where('user_id', $user->id)->where('name', 'Dropset Finishers')->firstOrFail();
        $dropset->load(['blocks.setGroups.dropsetSegments']);
        $working0 = $dropset->blocks[0]->setGroups->firstWhere('type', SetGroupType::Working);
        $this->assertSame([2], $working0->dropsetSegments->pluck('set_index')->unique()->values()->all());
        $this->assertCount(3, $working0->dropsetSegments);
        $working1 = $dropset->blocks[1]->setGroups->firstWhere('type', SetGroupType::Working);
        $this->assertSame([1], $working1->dropsetSegments->pluck('set_index')->unique()->values()->all());
    }

    private function seedCatalogExercises(): void
    {
        $group = MuscleGroup::factory()->create();

        $exercises = [
            ['Barbell Bench Press - Medium Grip', ExerciseEquipment::Barbell],
            ['Bent Over Barbell Row', ExerciseEquipment::Barbell],
            ['Barbell Squat', ExerciseEquipment::Barbell],
            ['Dumbbell Bench Press', ExerciseEquipment::Dumbbell],
            ['Arnold Dumbbell Press', ExerciseEquipment::Dumbbell],
            ['Alternate Hammer Curl', ExerciseEquipment::Dumbbell],
            ['Bench Dips', ExerciseEquipment::BodyOnly],
            ['Close-Grip Barbell Bench Press', ExerciseEquipment::Barbell],
            ['Barbell Curl', ExerciseEquipment::Barbell],
            ['Bent Over Two-Dumbbell Row', ExerciseEquipment::Dumbbell],
            ['Barbell Shoulder Press', ExerciseEquipment::Barbell],
        ];

        foreach ($exercises as [$name, $equipment]) {
            Exercise::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name),
                'user_id' => null,
                'primary_muscle_group_id' => $group->id,
                'equipment' => $equipment,
            ]);
        }
    }
}
