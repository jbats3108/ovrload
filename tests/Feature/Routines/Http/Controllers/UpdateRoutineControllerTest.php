<?php

namespace Tests\Feature\Routines\Http\Controllers;

use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Shared\Enums\WarmUpWeightMode;
use Database\Seeders\ExerciseProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\RoutineEditorPayload;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class UpdateRoutineControllerTest extends TestCase
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
    public function admins_cannot_update_user_routines(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();

        $this->actingAs($this->adminUser)->put(route('routines.update', $routine), [
            'name' => 'New Name',
            'blocks' => [],
        ])->assertForbidden();
    }

    #[Test]
    public function users_can_only_update_their_own_routines(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();

        $this->actingAs($this->secondUser)->put(route('routines.update', $routine), [
            'name' => 'New Name',
            'blocks' => [],
        ])->assertNotFound();
    }

    #[Test]
    public function owner_update_redirects_with_success(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'New Name',
            'deload_weight_factor' => 0.5,
            'deload_reps_factor' => 2,
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'working_weight_kg' => 80,
                    'working' => ['set_count' => 3, 'rest_seconds' => 180],
                ]),
            ],
        ])
            ->assertRedirect(route('routines.edit', $routine))
            ->assertSessionHas('success', 'Routine saved.');
    }

    #[Test]
    public function owner_update_rejects_a_draft_preset_as_the_default_profile(): void
    {
        $draft = ExerciseProfile::factory()->preset()->draft()->create(['name' => 'WIP']);
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'New Name',
            'default_exercise_profile_id' => $draft->id,
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'working_weight_kg' => 80,
                    'working' => ['set_count' => 3, 'rest_seconds' => 180],
                ]),
            ],
        ])->assertSessionHasErrors('blocks');
    }

    #[Test]
    public function owner_update_returns_inertia_location_for_inertia_requests(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->user)
            ->withHeaders(['X-Inertia' => 'true'])
            ->put(route('routines.update', $routine), [
                'name' => 'Saved Inertia',
                'blocks' => [
                    RoutineEditorPayload::block($exercise->id),
                ],
            ])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('routines.edit', $routine));

        $this->assertSame('Saved Inertia', $routine->fresh()->name);
    }

    #[Test]
    public function owner_can_save_routine_and_block_profile_assignments(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();
        $profile = ExerciseProfile::query()->where('slug', 'preset-strength')->firstOrFail();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Profile Routine',
            'default_exercise_profile_id' => $profile->id,
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'exercise_profile_id' => $profile->id,
                    'exercise_profile_fingerprint' => $profile->recipe()->fingerprint(),
                    'floor_is_derived' => true,
                    'shared_profile_id' => $profile->id,
                    'shared_profile_fingerprint' => $profile->recipe()->sharedFingerprint(),
                    'prescribed_reps' => $profile->target_reps,
                    'working' => ['set_count' => 3, 'rest_seconds' => $profile->working_rest_seconds],
                    'warm_up' => [
                        'set_count' => count($profile->warm_up_steps),
                        'rest_seconds' => 60,
                        'steps' => $profile->warm_up_steps,
                    ],
                ]),
            ],
        ])->assertRedirect(route('routines.edit', $routine));

        $savedRoutine = $routine->fresh(['defaultExerciseProfile', 'blocks.blockExercises.exerciseProfile', 'blocks.setGroups.warmUpSteps']);
        $savedBlock = $savedRoutine->blocks->firstOrFail();
        $savedExercise = $savedBlock->blockExercises->firstOrFail();
        $savedWarmUps = $savedBlock->setGroups->firstWhere('type', 'warm_up')?->warmUpSteps;

        $this->assertSame($profile->id, $savedRoutine->default_exercise_profile_id);
        $this->assertSame($profile->id, $savedExercise->exercise_profile_id);
        $this->assertSame($profile->id, $savedBlock->shared_exercise_profile_id);
        $this->assertSame($profile->recipe()->fingerprint(), $savedExercise->exercise_profile_fingerprint);
        $this->assertNotNull($savedWarmUps);
        $this->assertCount(4, $savedWarmUps);
        $this->assertSame(WarmUpWeightMode::Bar, $savedWarmUps->first()->weight_mode);
        $this->assertSame(10, $savedWarmUps->first()->reps);
    }

    #[Test]
    public function owner_cannot_assign_another_users_profile_to_a_routine(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();
        $otherProfile = ExerciseProfile::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Foreign Profile',
            'default_exercise_profile_id' => $otherProfile->id,
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'exercise_profile_id' => $otherProfile->id,
                    'shared_profile_id' => $otherProfile->id,
                ]),
            ],
        ])->assertSessionHasErrors('blocks');
    }

    #[Test]
    public function owner_can_save_a_routine_with_an_outdated_profile_copy(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();
        $profile = ExerciseProfile::factory()->forUser($this->user)->create([
            'target_reps' => 6,
            'floor_override' => null,
            'working_rest_seconds' => 120,
            'warm_up_steps' => [['percent' => 50, 'reps' => 5]],
        ]);
        $oldFingerprint = $profile->recipe()->fingerprint();
        $oldSharedFingerprint = $profile->recipe()->sharedFingerprint();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Outdated Profile',
            'default_exercise_profile_id' => $profile->id,
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'exercise_profile_id' => $profile->id,
                    'exercise_profile_fingerprint' => $oldFingerprint,
                    'floor_is_derived' => true,
                    'shared_profile_id' => $profile->id,
                    'shared_profile_fingerprint' => $oldSharedFingerprint,
                    'working' => ['set_count' => 3, 'rest_seconds' => 120],
                    'warm_up' => [
                        'set_count' => 1,
                        'rest_seconds' => 60,
                        'steps' => [['percent' => 50, 'reps' => 5]],
                    ],
                ]),
            ],
        ])->assertRedirect();

        $profile->update([
            'target_reps' => 10,
            'working_rest_seconds' => 180,
            'warm_up_steps' => [['percent' => 75, 'reps' => 3]],
            'recipe_fingerprint' => $profile->recipe()->fingerprint(),
        ]);

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Outdated Profile Saved',
            'default_exercise_profile_id' => $profile->id,
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'exercise_profile_id' => $profile->id,
                    'exercise_profile_fingerprint' => $oldFingerprint,
                    'floor_is_derived' => true,
                    'shared_profile_id' => $profile->id,
                    'shared_profile_fingerprint' => $oldSharedFingerprint,
                    'prescribed_reps' => 6,
                    'working' => ['set_count' => 3, 'rest_seconds' => 120],
                    'warm_up' => [
                        'set_count' => 1,
                        'rest_seconds' => 60,
                        'steps' => [['percent' => 50, 'reps' => 5]],
                    ],
                ]),
            ],
        ])->assertRedirect(route('routines.edit', $routine));

        $saved = $routine->fresh(['blocks.blockExercises']);
        $savedExercise = $saved->blocks->firstOrFail()->blockExercises->firstOrFail();
        $this->assertSame(6, $savedExercise->prescribed_reps);
        $this->assertSame($oldFingerprint, $savedExercise->exercise_profile_fingerprint);
    }

    #[Test]
    public function owner_update_treats_blank_rest_seconds_as_zero(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Zero Rest',
            'deload_weight_factor' => 0.5,
            'deload_reps_factor' => 2,
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'working_weight_kg' => 80,
                    'working' => ['set_count' => 3, 'rest_seconds' => null],
                    'warm_up' => ['set_count' => 0, 'rest_seconds' => '', 'steps' => []],
                ]),
            ],
        ])
            ->assertRedirect(route('routines.edit', $routine))
            ->assertSessionHas('success', 'Routine saved.');

        $block = $routine->fresh()->blocks()->first();
        $this->assertSame(0, $block->setGroups()->where('type', 'working')->value('rest_seconds'));
        $this->assertSame(0, $block->setGroups()->where('type', 'warm_up')->value('rest_seconds'));
    }

    #[Test]
    public function owner_update_rejects_stale_expected_updated_at(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Stale Save',
            'expected_updated_at' => now()->subMinute()->toIso8601String(),
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'working_weight_kg' => 80,
                    'working' => ['set_count' => 3, 'rest_seconds' => 180],
                ]),
            ],
        ])->assertSessionHasErrors('expected_updated_at');
    }

    #[Test]
    public function owner_can_save_progression_overrides(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Progression Overrides',
            'deload_weight_factor' => 0.9,
            'deload_reps_factor' => 1,
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'working_weight_kg' => 80,
                    'prescribed_reps' => 5,
                    'achievement_floor' => 3,
                    'progression_target' => null,
                    'working' => ['set_count' => 3, 'rest_seconds' => 180],
                ]),
            ],
        ])->assertRedirect(route('routines.edit', $routine));

        $row = $routine->fresh()->blocks()->first()->blockExercises()->first();
        $this->assertSame(3, $row->achievement_floor_override);
        $this->assertNull($row->progression_target_override);
    }

    #[Test]
    public function owner_can_save_deload_alternate(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $primary = Exercise::factory()->create();
        $alternate = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'With Deload Alternate',
            'deload_weight_factor' => 0.5,
            'deload_reps_factor' => 2,
            'blocks' => [
                RoutineEditorPayload::block($primary->id, [
                    'working_weight_kg' => 100,
                    'deload_exercise_id' => $alternate->id,
                    'deload_working_weight_kg' => 40,
                    'working' => ['set_count' => 3, 'rest_seconds' => 180],
                ]),
            ],
        ])->assertRedirect(route('routines.edit', $routine));

        $row = $routine->fresh()->blocks()->first()->blockExercises()->first();
        $this->assertSame($alternate->id, $row->deload_exercise_id);
        $this->assertSame(40000, $row->deload_working_weight_g);
    }

    #[Test]
    public function owner_update_rejects_deload_alternate_same_as_primary(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Bad Alternate',
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'deload_exercise_id' => $exercise->id,
                    'deload_working_weight_kg' => 40,
                ]),
            ],
        ])
            ->assertRedirect()
            ->assertSessionHasErrors();
    }

    #[Test]
    public function owner_update_rejects_deload_exercise_without_weight(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $primary = Exercise::factory()->create();
        $alternate = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Incomplete Alternate',
            'blocks' => [
                RoutineEditorPayload::block($primary->id, [
                    'deload_exercise_id' => $alternate->id,
                    'deload_working_weight_kg' => null,
                ]),
            ],
        ])
            ->assertRedirect()
            ->assertSessionHasErrors();
    }

    #[Test]
    public function editor_validation_errors_surface_on_blocks(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exerciseA = Exercise::factory()->create();
        $exerciseB = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Bad Superset Dropset',
            'blocks' => [
                RoutineEditorPayload::block($exerciseA->id, [
                    'is_superset' => true,
                    'exercises' => [
                        [
                            'exercise_id' => $exerciseA->id,
                            'working_weight_kg' => 60,
                            'prescribed_reps' => 6,
                        ],
                        [
                            'exercise_id' => $exerciseB->id,
                            'working_weight_kg' => 40,
                            'prescribed_reps' => 8,
                        ],
                    ],
                    'working' => [
                        'set_count' => 2,
                        'rest_seconds' => 120,
                        'dropsets' => [
                            [
                                'set_index' => 0,
                                'segments' => [
                                    ['weight_kg' => 60],
                                    ['weight_kg' => 40],
                                ],
                            ],
                        ],
                    ],
                ]),
            ],
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('blocks');
    }

    #[Test]
    public function edit_page_renders_dropset_props_for_owner(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Dropset Finisher',
            'blocks' => [
                RoutineEditorPayload::block($exercise->id, [
                    'working_weight_kg' => 20,
                    'prescribed_reps' => 12,
                    'working' => [
                        'set_count' => 2,
                        'rest_seconds' => 90,
                        'dropsets' => [
                            [
                                'set_index' => 1,
                                'segments' => [
                                    ['weight_kg' => 20],
                                    ['weight_kg' => 8],
                                ],
                            ],
                        ],
                    ],
                ]),
            ],
        ])->assertRedirect(route('routines.edit', $routine));

        $this->actingAs($this->user)
            ->get(route('routines.edit', $routine))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('routines/Edit')
                ->has('warm_up_defaults')
                ->has('warm_up_defaults_scope')
                ->has('achievement_floor_default')
                ->where('progression_target_default', 6)
                ->where('routine.blocks.0.working.dropsets.0.set_index', 1)
                ->where('routine.blocks.0.working.dropsets.0.segments.0.weight_kg', 20)
                ->where('routine.blocks.0.working.dropsets.0.segments.1.weight_kg', 8)
                ->loadDeferredProps(fn ($page) => $page->has('exercises')));
    }

    #[Test]
    public function edit_page_renders_fixed_warm_up_step_props_for_owner(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exercise = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
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
        ])->assertRedirect(route('routines.edit', $routine));

        $this->actingAs($this->user)
            ->get(route('routines.edit', $routine))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('routines/Edit')
                ->where('routine.blocks.0.warm_up.steps.0.mode', WarmUpWeightMode::Fixed->value)
                ->where('routine.blocks.0.warm_up.steps.0.weight_kg', 60)
                ->where('routine.blocks.0.warm_up.steps.0.percent', null)
                ->where('routine.blocks.0.warm_up.steps.0.reps', 5));
    }

    #[Test]
    public function owner_can_change_routine_default_profile_without_updating_blocks(): void
    {
        $custom = ExerciseProfile::factory()->forUser($this->user)->create([
            'name' => 'My Custom 1',
            'target_reps' => 12,
            'floor_override' => 10,
            'working_rest_seconds' => 90,
            'warm_up_steps' => [],
        ]);
        $strength = ExerciseProfile::query()->where('slug', 'preset-strength')->firstOrFail();
        $hypertrophy = ExerciseProfile::query()->where('slug', 'preset-hypertrophy')->firstOrFail();
        $routine = Routine::factory()->withUser($this->user)->create([
            'default_exercise_profile_id' => $custom->id,
        ]);
        $exerciseOne = Exercise::factory()->create();
        $exerciseTwo = Exercise::factory()->create();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => $routine->name,
            'default_exercise_profile_id' => $strength->id,
            'blocks' => [
                RoutineEditorPayload::block($exerciseOne->id, [
                    'is_superset' => true,
                    'shared_profile_id' => $custom->id,
                    'shared_profile_fingerprint' => $custom->recipe()->sharedFingerprint(),
                    'exercises' => [
                        [
                            'exercise_id' => $exerciseOne->id,
                            'exercise_profile_id' => $custom->id,
                            'exercise_profile_fingerprint' => $custom->recipe()->exerciseFingerprint(),
                            'working_weight_kg' => 15,
                            'prescribed_reps' => 12,
                            'achievement_floor' => 10,
                            'floor_is_derived' => false,
                            'progression_target' => null,
                            'deload_exercise_id' => null,
                            'deload_working_weight_kg' => null,
                        ],
                        [
                            'exercise_id' => $exerciseTwo->id,
                            'exercise_profile_id' => $hypertrophy->id,
                            'exercise_profile_fingerprint' => $hypertrophy->recipe()->exerciseFingerprint(),
                            'working_weight_kg' => 15,
                            'prescribed_reps' => 10,
                            'achievement_floor' => null,
                            'floor_is_derived' => true,
                            'progression_target' => null,
                            'deload_exercise_id' => null,
                            'deload_working_weight_kg' => null,
                        ],
                    ],
                    'working' => ['set_count' => 1, 'rest_seconds' => 90],
                    'warm_up' => ['set_count' => 0, 'rest_seconds' => 60, 'steps' => []],
                ]),
            ],
        ])->assertRedirect(route('routines.edit', $routine));

        $saved = $routine->fresh(['blocks.blockExercises']);
        $block = $saved->blocks->firstOrFail();

        $this->assertSame($strength->id, $saved->default_exercise_profile_id);
        $this->assertSame($custom->id, $block->shared_exercise_profile_id);
        $this->assertSame($custom->id, $block->blockExercises->firstWhere('position', 1)?->exercise_profile_id);
    }

    #[Test]
    public function owner_can_save_superset_exercises_with_derived_floors_and_current_profile_fingerprints(): void
    {
        $routine = Routine::factory()->withUser($this->user)->create();
        $exerciseOne = Exercise::factory()->create();
        $exerciseTwo = Exercise::factory()->create();
        $hypertrophy = ExerciseProfile::query()->where('slug', 'preset-hypertrophy')->firstOrFail();

        $this->actingAs($this->user)->put(route('routines.update', $routine), [
            'name' => 'Full Body A',
            'blocks' => [
                RoutineEditorPayload::block($exerciseOne->id, [
                    'is_superset' => true,
                    'shared_profile_id' => null,
                    'shared_profile_fingerprint' => null,
                    'exercises' => [
                        [
                            'exercise_id' => $exerciseOne->id,
                            'exercise_profile_id' => $hypertrophy->id,
                            'exercise_profile_fingerprint' => $hypertrophy->recipe()->exerciseFingerprint(),
                            'working_weight_kg' => 32.5,
                            'prescribed_reps' => 10,
                            'achievement_floor' => 8,
                            'floor_is_derived' => true,
                            'progression_target' => null,
                            'deload_exercise_id' => null,
                            'deload_working_weight_kg' => null,
                        ],
                        [
                            'exercise_id' => $exerciseTwo->id,
                            'exercise_profile_id' => ExerciseProfile::query()->where('slug', 'preset-strength')->value('id'),
                            'exercise_profile_fingerprint' => ExerciseProfile::query()->where('slug', 'preset-strength')->firstOrFail()->recipe()->exerciseFingerprint(),
                            'working_weight_kg' => 120,
                            'prescribed_reps' => 6,
                            'achievement_floor' => null,
                            'floor_is_derived' => true,
                            'progression_target' => null,
                            'deload_exercise_id' => null,
                            'deload_working_weight_kg' => null,
                        ],
                    ],
                    'working' => ['set_count' => 3, 'rest_seconds' => 120],
                    'warm_up' => ['set_count' => 0, 'rest_seconds' => 60, 'steps' => []],
                ]),
            ],
        ])->assertRedirect(route('routines.edit', $routine));

        $saved = $routine->fresh(['blocks.blockExercises']);
        $block = $saved->blocks->firstOrFail();
        $hypertrophyExercise = $block->blockExercises->firstWhere('position', 1);

        $this->assertNotNull($hypertrophyExercise);
        $this->assertSame($hypertrophy->id, $hypertrophyExercise->exercise_profile_id);
        $this->assertNull($hypertrophyExercise->achievement_floor_override);
        $this->assertTrue($hypertrophyExercise->floor_is_derived);
    }

    #[Test]
    public function edit_includes_archived_profiles_still_referenced_by_the_routine(): void
    {
        $archived = ExerciseProfile::factory()->forUser($this->user)->archived()->create(['name' => 'Old Push']);
        $routine = Routine::factory()->withUser($this->user)->create([
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

        $this->actingAs($this->user)
            ->get(route('routines.edit', $routine))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('routines/Edit')
                ->where('exercise_profiles', fn (array $profiles) => collect($profiles)->contains(
                    fn ($profile): bool => $profile['id'] === $archived->id && $profile['status'] === ExerciseProfileStatus::Archived->value,
                )));
    }
}
