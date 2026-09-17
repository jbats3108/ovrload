<?php

namespace Tests\Unit\Shared;

use App\Exercises\Enums\ExerciseEquipment;
use App\Shared\Enums\WarmUpWeightMode;
use App\Shared\Support\WarmUpStepSupport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WarmUpStepSupportTest extends TestCase
{
    #[Test]
    public function it_normalizes_percent_bar_and_fixed_steps(): void
    {
        $this->assertSame(
            ['mode' => WarmUpWeightMode::Percent, 'percent' => 50, 'weight_g' => null, 'reps' => 5],
            WarmUpStepSupport::normalize(['percent' => 50, 'reps' => 5]),
        );
        $this->assertSame(
            ['mode' => WarmUpWeightMode::Bar, 'percent' => null, 'weight_g' => null, 'reps' => 10],
            WarmUpStepSupport::normalize(['mode' => 'bar', 'reps' => 10]),
        );
        $this->assertSame(
            ['mode' => WarmUpWeightMode::Fixed, 'percent' => null, 'weight_g' => 60_000, 'reps' => 5],
            WarmUpStepSupport::normalize(['mode' => 'fixed', 'weight_kg' => 60, 'reps' => 5]),
        );
    }

    #[Test]
    public function normalize_rejects_non_arrays_and_invalid_reps(): void
    {
        $this->assertNull(WarmUpStepSupport::normalize(null));
        $this->assertNull(WarmUpStepSupport::normalize('percent'));
        $this->assertNull(WarmUpStepSupport::normalize(['percent' => 50]));
        $this->assertNull(WarmUpStepSupport::normalize(['percent' => 50, 'reps' => 0]));
    }

    #[Test]
    public function normalize_accepts_enum_mode_and_falls_back_for_unknown_modes(): void
    {
        $this->assertSame(
            ['mode' => WarmUpWeightMode::Bar, 'percent' => null, 'weight_g' => null, 'reps' => 8],
            WarmUpStepSupport::normalize(['mode' => WarmUpWeightMode::Bar, 'reps' => 8]),
        );
        $this->assertSame(
            ['mode' => WarmUpWeightMode::Percent, 'percent' => 40, 'weight_g' => null, 'reps' => 5],
            WarmUpStepSupport::normalize(['mode' => 'not-a-mode', 'percent' => 40, 'reps' => 5]),
        );
    }

    #[Test]
    public function normalize_rejects_fixed_steps_without_a_positive_weight(): void
    {
        $this->assertNull(WarmUpStepSupport::normalize(['mode' => 'fixed', 'reps' => 5]));
        $this->assertNull(WarmUpStepSupport::normalize(['mode' => 'fixed', 'weight_kg' => 0, 'reps' => 5]));
        $this->assertNull(WarmUpStepSupport::normalize(['mode' => 'fixed', 'weight_g' => null, 'reps' => 5]));
    }

    #[Test]
    public function normalize_accepts_fixed_steps_at_the_one_gram_boundary(): void
    {
        $this->assertSame(
            ['mode' => WarmUpWeightMode::Fixed, 'percent' => null, 'weight_g' => 1, 'reps' => 5],
            WarmUpStepSupport::normalize(['mode' => 'fixed', 'weight_g' => 1, 'reps' => 5]),
        );
    }

    #[Test]
    public function normalize_casts_string_weights_to_grams(): void
    {
        $this->assertSame(
            ['mode' => WarmUpWeightMode::Fixed, 'percent' => null, 'weight_g' => 45_000, 'reps' => 3],
            WarmUpStepSupport::normalize(['mode' => 'fixed', 'weight_g' => '45000', 'reps' => 3]),
        );
        $this->assertSame(
            ['mode' => WarmUpWeightMode::Fixed, 'percent' => null, 'weight_g' => 60_000, 'reps' => 3],
            WarmUpStepSupport::normalize(['mode' => 'fixed', 'weight_kg' => '60', 'reps' => 3]),
        );
    }

    #[Test]
    public function normalize_prefers_weight_g_for_fixed_steps(): void
    {
        $this->assertSame(
            ['mode' => WarmUpWeightMode::Fixed, 'percent' => null, 'weight_g' => 45_000, 'reps' => 3],
            WarmUpStepSupport::normalize(['mode' => 'fixed', 'weight_g' => 45_000, 'weight_kg' => 99, 'reps' => 3]),
        );
    }

    #[Test]
    public function normalize_rejects_percent_steps_without_a_positive_percent(): void
    {
        $this->assertNull(WarmUpStepSupport::normalize(['mode' => 'percent', 'reps' => 5]));
        $this->assertNull(WarmUpStepSupport::normalize(['mode' => 'percent', 'percent' => 0, 'reps' => 5]));
        $this->assertNull(WarmUpStepSupport::normalize(['reps' => 5]));
    }

    #[Test]
    public function normalize_list_drops_invalid_entries(): void
    {
        $this->assertSame(
            [
                ['mode' => WarmUpWeightMode::Percent, 'percent' => 50, 'weight_g' => null, 'reps' => 5],
                ['mode' => WarmUpWeightMode::Bar, 'percent' => null, 'weight_g' => null, 'reps' => 3],
            ],
            WarmUpStepSupport::normalizeList([
                ['percent' => 50, 'reps' => 5],
                null,
                ['mode' => 'bar', 'reps' => 0],
                ['mode' => 'bar', 'reps' => 3],
            ]),
        );
    }

    #[Test]
    public function to_storage_uses_weight_kg_when_weight_g_is_absent(): void
    {
        $this->assertSame(
            ['mode' => 'fixed', 'weight_kg' => 60.0, 'reps' => 5],
            WarmUpStepSupport::toStorage([
                'mode' => WarmUpWeightMode::Fixed,
                'percent' => null,
                'weight_g' => 60_000,
                'reps' => 5,
            ]),
        );
    }

    #[Test]
    public function to_storage_falls_back_to_zero_kg_when_fixed_weight_is_missing(): void
    {
        $this->assertSame(
            ['mode' => 'fixed', 'weight_kg' => 0.0, 'reps' => 5],
            WarmUpStepSupport::toStorage([
                'mode' => WarmUpWeightMode::Fixed,
                'percent' => null,
                'weight_g' => null,
                'reps' => 5,
            ]),
        );
    }

    #[Test]
    public function it_resolves_fixed_target_weight_without_using_working_weight(): void
    {
        $weight = WarmUpStepSupport::targetWeightG(
            WarmUpWeightMode::Fixed,
            null,
            200_000,
            20_000,
            ExerciseEquipment::Barbell,
            60_000,
        );

        $this->assertSame(60_000, $weight);
    }

    #[Test]
    public function it_resolves_bar_target_weight_from_plate_profile_bar_weight(): void
    {
        $weight = WarmUpStepSupport::targetWeightG(
            WarmUpWeightMode::Bar,
            null,
            100_000,
            20_000,
            ExerciseEquipment::Barbell,
        );

        $this->assertSame(20_000, $weight);
    }

    #[Test]
    public function bar_steps_without_barbell_equipment_have_no_target_weight(): void
    {
        $weight = WarmUpStepSupport::targetWeightG(
            WarmUpWeightMode::Bar,
            null,
            100_000,
            20_000,
            ExerciseEquipment::Dumbbell,
        );

        $this->assertNull($weight);
    }

    #[Test]
    public function bar_steps_without_equipment_have_no_target_weight(): void
    {
        $weight = WarmUpStepSupport::targetWeightG(
            WarmUpWeightMode::Bar,
            null,
            100_000,
            20_000,
            null,
        );

        $this->assertNull($weight);
    }

    #[Test]
    public function fixed_target_weight_rejects_null_and_non_positive_values(): void
    {
        $this->assertNull(WarmUpStepSupport::targetWeightG(
            WarmUpWeightMode::Fixed,
            null,
            200_000,
            20_000,
            ExerciseEquipment::Barbell,
            null,
        ));
        $this->assertNull(WarmUpStepSupport::targetWeightG(
            WarmUpWeightMode::Fixed,
            null,
            200_000,
            20_000,
            ExerciseEquipment::Barbell,
            0,
        ));
    }

    #[Test]
    public function percent_target_weight_uses_exact_percentage_math(): void
    {
        $this->assertSame(
            50_000,
            WarmUpStepSupport::targetWeightG(
                WarmUpWeightMode::Percent,
                50,
                100_000,
                20_000,
                ExerciseEquipment::Barbell,
            ),
        );
        $this->assertSame(
            33_000,
            WarmUpStepSupport::targetWeightG(
                WarmUpWeightMode::Percent,
                33,
                100_001,
                20_000,
                ExerciseEquipment::Barbell,
            ),
        );
        $this->assertSame(
            50_000,
            WarmUpStepSupport::targetWeightG(
                WarmUpWeightMode::Percent,
                50,
                99_999,
                20_000,
                ExerciseEquipment::Barbell,
            ),
        );
    }

    #[Test]
    public function percent_target_weight_rejects_null_and_non_positive_percents(): void
    {
        $this->assertNull(WarmUpStepSupport::targetWeightG(
            WarmUpWeightMode::Percent,
            null,
            100_000,
            20_000,
            ExerciseEquipment::Barbell,
        ));
        $this->assertNull(WarmUpStepSupport::targetWeightG(
            WarmUpWeightMode::Percent,
            0,
            100_000,
            20_000,
            ExerciseEquipment::Barbell,
        ));
        $this->assertSame(
            1_000,
            WarmUpStepSupport::targetWeightG(
                WarmUpWeightMode::Percent,
                1,
                100_000,
                20_000,
                ExerciseEquipment::Barbell,
            ),
        );
    }
}
