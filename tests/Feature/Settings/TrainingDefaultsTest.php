<?php

namespace Tests\Feature\Settings;

use App\Users\Enums\ProgressionStyle;
use App\Users\Enums\ProgressiveMidBlock;
use App\Users\Enums\WarmUpDefaultsScope;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\UserHelper;
use Tests\TestCase;

class TrainingDefaultsTest extends TestCase
{
    use RefreshDatabase;
    use UserHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedUsers(withCatalogAndRoutines: false);
    }

    #[Test]
    public function it_renders_resolved_warm_up_defaults(): void
    {
        $response = $this->actingAs($this->user)->get(route('training.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('settings/Training')
            ->where('using_app_fallback', true)
            ->where('warm_up_defaults_scope', 'all_blocks')
            ->where('achievement_floor_default', null)
            ->where('progression_target_default', 6)
            ->where('progression_style_default', 'straight_sets')
            ->where('progressive_mid_block_default', 'ask')
            ->where('deload_weight_factor_default', 0.5)
            ->where('deload_reps_factor_default', 2)
            ->where('deload_every_n_default', 3)
            ->has('warm_up_steps_default', 3)
            ->has('plate_profile'));
    }

    #[Test]
    public function it_saves_warm_up_step_defaults(): void
    {
        $response = $this->actingAs($this->user)->put(route('training.update'), [
            'warm_up_steps_default' => [
                ['percent' => 45, 'reps' => 6],
                ['percent' => 70, 'reps' => 2],
            ],
            'warm_up_defaults_scope' => 'first_block',
        ]);

        $response->assertRedirect(route('training.edit'));
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertSame(
            [
                ['mode' => 'percent', 'percent' => 45, 'reps' => 6],
                ['mode' => 'percent', 'percent' => 70, 'reps' => 2],
            ],
            $this->user->warm_up_steps_default
        );
        $this->assertSame(
            [
                ['mode' => 'percent', 'percent' => 45, 'reps' => 6],
                ['mode' => 'percent', 'percent' => 70, 'reps' => 2],
            ],
            $this->user->resolvedWarmUpStepsDefault()
        );
        $this->assertSame(WarmUpDefaultsScope::FirstBlock, $this->user->warm_up_defaults_scope);
    }

    #[Test]
    public function it_saves_fixed_weight_warm_up_step_defaults(): void
    {
        $response = $this->actingAs($this->user)->put(route('training.update'), [
            'warm_up_steps_default' => [
                ['mode' => 'fixed', 'weight_kg' => 60, 'reps' => 5],
            ],
            'warm_up_defaults_scope' => 'all_blocks',
        ]);

        $response->assertRedirect(route('training.edit'));

        $this->user->refresh();
        $this->assertEquals(
            [
                ['mode' => 'fixed', 'weight_kg' => 60, 'reps' => 5],
            ],
            $this->user->warm_up_steps_default
        );
        $this->assertEquals(
            [
                ['mode' => 'fixed', 'weight_kg' => 60, 'reps' => 5],
            ],
            $this->user->resolvedWarmUpStepsDefault()
        );
    }

    #[Test]
    public function it_saves_achievement_floor_default(): void
    {
        $response = $this->actingAs($this->user)->put(route('training.update'), [
            'warm_up_steps_default' => [
                ['percent' => 40, 'reps' => 5],
            ],
            'warm_up_defaults_scope' => 'all_blocks',
            'achievement_floor_default' => 1,
            'progression_style_default' => 'straight_sets',
            'progressive_mid_block_default' => 'ask',
        ]);

        $response->assertRedirect(route('training.edit'));

        $this->user->refresh();
        $this->assertSame(1, $this->user->achievement_floor_default);
    }

    #[Test]
    public function it_saves_progression_target_default(): void
    {
        $response = $this->actingAs($this->user)->put(route('training.update'), [
            'warm_up_steps_default' => [
                ['percent' => 40, 'reps' => 5],
            ],
            'warm_up_defaults_scope' => 'all_blocks',
            'progression_target_default' => 8,
            'progression_style_default' => 'straight_sets',
            'progressive_mid_block_default' => 'ask',
        ]);

        $response->assertRedirect(route('training.edit'));

        $this->user->refresh();
        $this->assertSame(8, $this->user->progression_target_default);
        $this->assertSame(8, $this->user->resolvedDefaultTargetReps());
    }

    #[Test]
    public function it_rejects_out_of_range_progression_target_default(): void
    {
        $this->actingAs($this->user)->put(route('training.update'), [
            'warm_up_steps_default' => [
                ['percent' => 40, 'reps' => 5],
            ],
            'warm_up_defaults_scope' => 'all_blocks',
            'progression_target_default' => 0,
            'progression_style_default' => 'straight_sets',
            'progressive_mid_block_default' => 'ask',
        ])->assertSessionHasErrors('progression_target_default');
    }

    #[Test]
    public function it_clears_achievement_floor_when_omitted_as_null(): void
    {
        $this->user->update([
            'achievement_floor_default' => 2,
        ]);

        $response = $this->actingAs($this->user)->put(route('training.update'), [
            'warm_up_steps_default' => [
                ['percent' => 40, 'reps' => 5],
            ],
            'warm_up_defaults_scope' => 'all_blocks',
            'achievement_floor_default' => null,
            'progression_style_default' => 'straight_sets',
            'progressive_mid_block_default' => 'ask',
        ]);

        $response->assertRedirect(route('training.edit'));

        $this->user->refresh();
        $this->assertNull($this->user->achievement_floor_default);
    }

    #[Test]
    public function it_saves_progression_style_defaults(): void
    {
        $response = $this->actingAs($this->user)->put(route('training.update'), [
            'warm_up_steps_default' => [
                ['percent' => 40, 'reps' => 5],
            ],
            'warm_up_defaults_scope' => 'all_blocks',
            'progression_style_default' => 'progressive_overload',
            'progressive_mid_block_default' => 'auto',
        ]);

        $response->assertRedirect(route('training.edit'));

        $this->user->refresh();
        $this->assertSame(ProgressionStyle::ProgressiveOverload, $this->user->progression_style_default);
        $this->assertSame(ProgressiveMidBlock::Auto, $this->user->progressive_mid_block_default);
    }

    #[Test]
    public function it_saves_deload_defaults(): void
    {
        $response = $this->actingAs($this->user)->put(route('training.update'), [
            'warm_up_steps_default' => [
                ['percent' => 40, 'reps' => 5],
            ],
            'warm_up_defaults_scope' => 'all_blocks',
            'progression_style_default' => 'straight_sets',
            'progressive_mid_block_default' => 'ask',
            'deload_weight_factor_default' => 0.7,
            'deload_reps_factor_default' => 1.5,
            'deload_every_n_default' => 4,
        ]);

        $response->assertRedirect(route('training.edit'));

        $this->user->refresh();
        $this->assertSame('0.700', (string) $this->user->deload_weight_factor_default);
        $this->assertSame('1.500', (string) $this->user->deload_reps_factor_default);
        $this->assertSame(4, $this->user->deload_every_n_default);
    }

    #[Test]
    public function it_rejects_out_of_range_deload_defaults(): void
    {
        $this->actingAs($this->user)->put(route('training.update'), [
            'warm_up_steps_default' => [
                ['percent' => 40, 'reps' => 5],
            ],
            'warm_up_defaults_scope' => 'all_blocks',
            'progression_style_default' => 'straight_sets',
            'progressive_mid_block_default' => 'ask',
            'deload_weight_factor_default' => 5.1,
            'deload_reps_factor_default' => 1,
            'deload_every_n_default' => 3,
        ])->assertSessionHasErrors('deload_weight_factor_default');

        $this->actingAs($this->user)->put(route('training.update'), [
            'warm_up_steps_default' => [
                ['percent' => 40, 'reps' => 5],
            ],
            'warm_up_defaults_scope' => 'all_blocks',
            'progression_style_default' => 'straight_sets',
            'progressive_mid_block_default' => 'ask',
            'deload_weight_factor_default' => 0.5,
            'deload_reps_factor_default' => 1,
            'deload_every_n_default' => 100,
        ])->assertSessionHasErrors('deload_every_n_default');
    }

    #[Test]
    public function it_resets_to_app_fallback(): void
    {
        $this->user->update([
            'warm_up_steps_default' => [['percent' => 50, 'reps' => 8]],
        ]);

        $response = $this->actingAs($this->user)->post(route('training.reset'));

        $response->assertRedirect(route('training.edit'));
        $this->assertNull($this->user->fresh()->warm_up_steps_default);
        $this->assertSame(User::fallbackWarmUpSteps(), $this->user->fresh()->resolvedWarmUpStepsDefault());
    }

    #[Test]
    public function guests_are_redirected_from_training_mutations(): void
    {
        $this->put(route('training.update'), [
            'warm_up_steps_default' => [['percent' => 40, 'reps' => 5]],
            'warm_up_defaults_scope' => WarmUpDefaultsScope::AllBlocks->value,
            'progression_style_default' => ProgressionStyle::StraightSets->value,
            'progressive_mid_block_default' => ProgressiveMidBlock::Ask->value,
        ])->assertRedirect(route('login'));

        $this->post(route('training.reset'))->assertRedirect(route('login'));
    }
}
