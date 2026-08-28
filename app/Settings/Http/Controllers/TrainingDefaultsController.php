<?php

namespace App\Settings\Http\Controllers;

use App\Shared\Http\Controllers\Controller;
use App\Users\Data\UpdateTrainingDefaultsData;
use App\Users\Enums\ProgressionStyle;
use App\Users\Enums\ProgressiveMidBlock;
use App\Users\Enums\WarmUpDefaultsScope;
use App\Users\Models\User;
use App\Users\Services\PlateProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainingDefaultsController extends Controller
{
    public function edit(Request $request, PlateProfileService $profiles): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/Training', [
            'warm_up_steps_default' => $user->resolvedWarmUpStepsDefault(),
            'warm_up_defaults_scope' => ($user->warm_up_defaults_scope ?? WarmUpDefaultsScope::AllBlocks)->value,
            'using_app_fallback' => $user->warm_up_steps_default === null,
            'achievement_floor_default' => $user->achievement_floor_default,
            'progression_target_default' => $user->resolvedDefaultTargetReps(),
            'progression_style_default' => ($user->progression_style_default ?? ProgressionStyle::StraightSets)->value,
            'progressive_mid_block_default' => ($user->progressive_mid_block_default ?? ProgressiveMidBlock::Ask)->value,
            'deload_weight_factor_default' => (float) $user->deload_weight_factor_default,
            'deload_reps_factor_default' => (float) $user->deload_reps_factor_default,
            'deload_every_n_default' => (int) $user->deload_every_n_default,
            'plate_profile' => $profiles->profilePayloadFor($user),
        ]);
    }

    public function update(UpdateTrainingDefaultsData $data, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $steps = $data->warmUpStepsDefault === null
            ? []
            : array_values(array_map(
                static fn ($step): array => [
                    'percent' => min(100, max(1, $step->percent)),
                    'reps' => min(100, max(1, $step->reps)),
                ],
                $data->warmUpStepsDefault->all()
            ));

        $user->warm_up_steps_default = $steps;
        $user->warm_up_defaults_scope = $data->warmUpDefaultsScope;
        $user->achievement_floor_default = $data->achievementFloorDefault;
        $user->progression_target_default = $data->progressionTargetDefault;
        $user->progression_style_default = $data->progressionStyleDefault;
        $user->progressive_mid_block_default = $data->progressiveMidBlockDefault;
        $user->deload_weight_factor_default = $data->deloadWeightFactorDefault;
        $user->deload_reps_factor_default = $data->deloadRepsFactorDefault;
        $user->deload_every_n_default = $data->deloadEveryNDefault;
        $user->save();

        return redirect()
            ->route('training.edit')
            ->with('success', 'Training defaults saved.');
    }

    public function reset(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->warm_up_steps_default = null;
        $user->save();

        return redirect()
            ->route('training.edit')
            ->with('success', 'Warm-up defaults reset to app fallback.');
    }
}
