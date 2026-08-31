<?php

namespace Tests\Unit\Routines\Services;

use App\Exercises\Models\Exercise;
use App\Routines\Data\Editor\SyncRoutineData;
use App\Routines\Models\Routine;
use App\Routines\Services\RoutineDuplicator;
use App\Routines\Services\RoutineEditorService;
use App\Shared\Enums\SetGroupType;
use App\Shared\Enums\WarmUpWeightMode;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\RoutineEditorPayload;
use Tests\TestCase;

class RoutineDuplicatorTest extends TestCase
{
    use RefreshDatabase;

    private RoutineDuplicator $duplicator;

    private RoutineEditorService $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->duplicator = app(RoutineDuplicator::class);
        $this->editor = app(RoutineEditorService::class);
    }

    #[Test]
    public function duplicate_copies_structure_deload_and_names_with_copy_suffix(): void
    {
        $owner = User::factory()->create();
        $source = Routine::factory()->withUser($owner)->create([
            'name' => 'Barbell Strength',
            'deload_weight_factor' => 0.7,
            'deload_reps_factor' => 1.5,
            'deload_every_n' => 4,
        ]);
        $squat = Exercise::factory()->create();
        $squatDeload = Exercise::factory()->create();
        $bench = Exercise::factory()->create();
        $row = Exercise::factory()->create();

        $this->editor->sync($source, SyncRoutineData::from([
            'name' => 'Barbell Strength',
            'deload_weight_factor' => 0.7,
            'deload_reps_factor' => 1.5,
            'deload_every_n' => 4,
            'blocks' => [
                [
                    'is_superset' => false,
                    'has_setup_after' => true,
                    'has_setup_after_warm_up' => true,
                    'exercises' => [
                        [
                            'exercise_id' => $squat->id,
                            'working_weight_kg' => 100,
                            'prescribed_reps' => 5,
                            'achievement_floor' => 3,
                            'progression_target' => 5,
                            'deload_exercise_id' => $squatDeload->id,
                            'deload_working_weight_kg' => 40,
                        ],
                    ],
                    'working' => [
                        'set_count' => 3,
                        'rest_seconds' => 180,
                        'dropsets' => [
                            [
                                'set_index' => 2,
                                'segments' => [
                                    ['weight_kg' => 80],
                                    ['weight_kg' => 60],
                                ],
                            ],
                        ],
                    ],
                    'warm_up' => [
                        'set_count' => 2,
                        'rest_seconds' => 60,
                        'steps' => [
                            ['percent' => 50, 'reps' => 5, 'has_setup_after' => true],
                            ['percent' => 75, 'reps' => 3, 'has_setup_after' => false],
                        ],
                    ],
                ],
                [
                    'is_superset' => true,
                    'has_setup_after' => false,
                    'has_setup_after_warm_up' => false,
                    'exercises' => [
                        [
                            'exercise_id' => $bench->id,
                            'working_weight_kg' => 60,
                            'prescribed_reps' => 8,
                        ],
                        [
                            'exercise_id' => $row->id,
                            'working_weight_kg' => 40,
                            'prescribed_reps' => 10,
                        ],
                    ],
                    'working' => ['set_count' => 3, 'rest_seconds' => 90],
                    'warm_up' => ['set_count' => 0, 'rest_seconds' => 45, 'steps' => []],
                ],
            ],
        ]));

        $source->refresh();
        $copy = $this->duplicator->duplicate($source, $owner);

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame($owner->id, $copy->user_id);
        $this->assertSame('Barbell Strength (copy)', $copy->name);
        $this->assertNotSame($source->slug, $copy->slug);
        $this->assertSame('0.700', (string) $copy->deload_weight_factor);
        $this->assertSame('1.500', (string) $copy->deload_reps_factor);
        $this->assertSame(4, $copy->deload_every_n);
        $this->assertCount(2, $copy->blocks);

        $first = $copy->blocks->sortBy('position')->values()[0];
        $this->assertFalse($first->is_superset);
        $this->assertTrue($first->has_setup_after);
        $this->assertTrue($first->has_setup_after_warm_up);
        $exercise = $first->blockExercises->first();
        $this->assertSame($squat->id, $exercise->exercise_id);
        $this->assertSame(100000, $exercise->working_weight_g);
        $this->assertSame($squatDeload->id, $exercise->deload_exercise_id);
        $this->assertSame(40000, $exercise->deload_working_weight_g);
        $this->assertSame(5, $exercise->prescribed_reps);
        $this->assertSame(3, $exercise->achievement_floor_override);
        $this->assertSame(5, $exercise->progression_target_override);

        $first->load(['setGroups.warmUpSteps', 'setGroups.dropsetSegments']);
        $warmGroup = $first->setGroups->firstWhere('type', SetGroupType::WarmUp);
        $workingGroup = $first->setGroups->firstWhere('type', SetGroupType::Working);
        $warmSteps = $warmGroup->warmUpSteps;
        $this->assertCount(2, $warmSteps);
        $this->assertSame(50, $warmSteps[0]->percent_of_working);
        $this->assertTrue($warmSteps[0]->has_setup_after);
        $this->assertSame(60, $warmGroup->rest_seconds);

        $this->assertSame(3, $workingGroup->set_count);
        $this->assertSame(180, $workingGroup->rest_seconds);
        $segments = $workingGroup->dropsetSegments;
        $this->assertCount(2, $segments);
        $this->assertSame(2, $segments[0]->set_index);
        $this->assertSame(80000, $segments[0]->weight_g);

        $second = $copy->blocks->sortBy('position')->values()[1];
        $this->assertTrue($second->is_superset);
        $this->assertCount(2, $second->blockExercises);
        $this->assertSame($bench->id, $second->blockExercises[0]->exercise_id);
        $this->assertSame($row->id, $second->blockExercises[1]->exercise_id);

        $this->assertSame(1, Routine::query()->whereKey($source->id)->count());
        $this->assertSame(2, $source->blocks()->count());
    }

    #[Test]
    public function duplicate_keeps_copy_suffix_when_already_present(): void
    {
        $owner = User::factory()->create();
        $source = Routine::factory()->withUser($owner)->create(['name' => 'Push (copy)']);

        $copy = $this->duplicator->duplicate($source, $owner);

        $this->assertSame('Push (copy)', $copy->name);
        $this->assertNotSame($source->slug, $copy->slug);
    }

    #[Test]
    public function duplicate_copies_fixed_weight_warm_up_steps(): void
    {
        $owner = User::factory()->create();
        $source = Routine::factory()->withUser($owner)->create(['name' => 'Deadlift']);
        $exercise = Exercise::factory()->create();

        $this->editor->sync($source, SyncRoutineData::from([
            'name' => 'Deadlift',
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'warm_up' => [
                        'set_count' => 1,
                        'rest_seconds' => 60,
                        'steps' => [
                            ['mode' => 'fixed', 'weight_kg' => 60, 'reps' => 5],
                        ],
                    ],
                ]),
            ],
        ]));

        $copy = $this->duplicator->duplicate($source->fresh(), $owner);
        $step = $copy->blocks->first()->warmUpSetGroup->warmUpSteps->first();

        $this->assertSame(WarmUpWeightMode::Fixed, $step->weight_mode);
        $this->assertSame(60_000, $step->weight_g);
        $this->assertNull($step->percent_of_working);
        $this->assertSame(5, $step->reps);
    }

    #[Test]
    public function duplicate_rejects_foreign_custom_exercises_for_new_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $source = Routine::factory()->withUser($other)->create();
        $foreign = Exercise::factory()->create(['user_id' => $other->id]);

        // Bypass editor availability checks by inserting rows directly.
        $block = $source->blocks()->create([
            'position' => 1,
            'is_superset' => false,
            'has_setup_after' => false,
            'has_setup_after_warm_up' => false,
        ]);
        $block->blockExercises()->create([
            'exercise_id' => $foreign->id,
            'position' => 1,
            'working_weight_g' => 20000,
            'prescribed_reps' => 8,
        ]);
        $block->setGroups()->create([
            'type' => 'working',
            'set_count' => 3,
            'rest_seconds' => 90,
        ]);
        $block->setGroups()->create([
            'type' => 'warm_up',
            'set_count' => 0,
            'rest_seconds' => 60,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Exercise {$foreign->id} is not available for this routine.");

        $this->duplicator->duplicate($source, $owner);
    }
}
