<?php

namespace App\ExerciseProfiles\Services;

use App\ExerciseProfiles\Data\AdminExerciseProfileData;
use App\ExerciseProfiles\Data\AdminExerciseProfilePageData;
use App\ExerciseProfiles\Data\ExerciseProfileWarmUpStepData;
use App\ExerciseProfiles\Data\SaveExerciseProfileData;
use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Exceptions\ExerciseProfileNotEditableException;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Shared\Support\WarmUpStepSupport;
use App\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\LaravelData\DataCollection;

final readonly class ExerciseProfilePresetService
{
    public function adminPageData(): AdminExerciseProfilePageData
    {
        $profiles = ExerciseProfile::query()
            ->whereNull('user_id')
            ->where('kind', ExerciseProfileKind::Preset)
            ->orderBy('status')
            ->orderBy('name')
            ->get();

        return new AdminExerciseProfilePageData(
            drafts: AdminExerciseProfileData::collect(
                $profiles->where('status', ExerciseProfileStatus::Draft)
                    ->map(fn (ExerciseProfile $profile): AdminExerciseProfileData => AdminExerciseProfileData::fromProfile($profile))
                    ->values(),
                DataCollection::class,
            ),
            published: AdminExerciseProfileData::collect(
                $profiles->where('status', ExerciseProfileStatus::Published)
                    ->map(fn (ExerciseProfile $profile): AdminExerciseProfileData => AdminExerciseProfileData::fromProfile($profile))
                    ->values(),
                DataCollection::class,
            ),
        );
    }

    public function createPreset(User $admin, SaveExerciseProfileData $data): ExerciseProfile
    {
        $this->assertAdmin($admin);
        $name = $this->adminName($data->name);
        $recipe = $this->recipeFromData($data);

        return ExerciseProfile::create([
            'user_id' => null,
            'created_by_user_id' => $admin->id,
            'kind' => ExerciseProfileKind::Preset,
            'status' => ExerciseProfileStatus::Draft,
            'name' => $name,
            'slug' => null,
            'slug_scope' => 'system',
            'target_reps' => $recipe->targetReps,
            'floor_override' => $recipe->floorOverride,
            'working_rest_seconds' => $recipe->workingRestSeconds,
            'warm_up_steps' => $recipe->warmUpSteps,
            'recipe_fingerprint' => $recipe->fingerprint(),
            'published_at' => null,
        ]);
    }

    public function updatePresetDraft(User $admin, ExerciseProfile $profile, SaveExerciseProfileData $data): ExerciseProfile
    {
        $this->assertAdmin($admin);
        $this->assertPresetDraft($profile);
        $recipe = $this->recipeFromData($data);

        $profile->update([
            'name' => $this->adminName($data->name),
            'target_reps' => $recipe->targetReps,
            'floor_override' => $recipe->floorOverride,
            'working_rest_seconds' => $recipe->workingRestSeconds,
            'warm_up_steps' => $recipe->warmUpSteps,
            'recipe_fingerprint' => $recipe->fingerprint(),
        ]);

        return $profile->fresh() ?? $profile;
    }

    public function deletePresetDraft(User $admin, ExerciseProfile $profile): void
    {
        $this->assertAdmin($admin);
        $this->assertPresetDraft($profile);
        $profile->forceDelete();
    }

    public function publishPreset(User $admin, ExerciseProfile $profile): ExerciseProfile
    {
        $this->assertAdmin($admin);

        return DB::transaction(function () use ($profile): ExerciseProfile {
            $locked = ExerciseProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $this->assertPresetDraft($locked);
            ExerciseProfile::query()->where('slug_scope', 'system')->lockForUpdate()->get();

            $this->assertPublishedPresetNameAvailable($locked);
            $this->assertPublishedPresetRecipeAvailable($locked);
            $slug = $this->publishedPresetSlug($locked->name);

            $locked->update([
                'status' => ExerciseProfileStatus::Published,
                'slug' => $slug,
                'published_at' => now(),
            ]);

            return $locked->fresh() ?? $locked;
        });
    }

    private function recipeFromData(SaveExerciseProfileData $data): ExerciseProfileRecipe
    {
        $steps = $data->warmUpSteps?->all() ?? [];

        return new ExerciseProfileRecipe(
            targetReps: $data->targetReps,
            floorOverride: $data->floorOverride,
            workingRestSeconds: $data->workingRestSeconds,
            warmUpSteps: array_values(array_map(
                WarmUpStepSupport::toStorage(...),
                WarmUpStepSupport::normalizeList(array_values(array_map(
                    static fn (ExerciseProfileWarmUpStepData $step): array => [
                        'mode' => $step->mode->value,
                        'percent' => $step->percent,
                        'weight_kg' => $step->weightKg,
                        'reps' => $step->reps,
                    ],
                    $steps,
                ))),
            )),
        );
    }

    private function assertAdmin(User $admin): void
    {
        if (! $admin->isAdmin()) {
            throw new ExerciseProfileNotEditableException('Only admins can manage presets.');
        }
    }

    private function assertPresetDraft(ExerciseProfile $profile): void
    {
        if (
            ! $profile->isPreset()
            || $profile->user_id !== null
            || $profile->status !== ExerciseProfileStatus::Draft
        ) {
            throw new ExerciseProfileNotEditableException('Only unpublished preset drafts can be changed.');
        }
    }

    private function adminName(string $name): string
    {
        $name = $name |> trim(...);
        $normalized = mb_strtolower($name);
        if ($name === '') {
            throw new InvalidArgumentException('Preset name is required.');
        }

        if (str_starts_with($normalized, 'ovrload ')) {
            throw new InvalidArgumentException('Enter the preset name without the OVRLOAD prefix.');
        }

        return $name;
    }

    private function assertPublishedPresetNameAvailable(ExerciseProfile $profile): void
    {
        $normalized = mb_strtolower($profile->name);
        $exists = ExerciseProfile::query()
            ->whereNull('user_id')
            ->where('kind', ExerciseProfileKind::Preset)
            ->where('status', ExerciseProfileStatus::Published)
            ->whereRaw('LOWER(name) = ?', [$normalized])
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('A published preset already uses that name.');
        }
    }

    private function assertPublishedPresetRecipeAvailable(ExerciseProfile $profile): void
    {
        $exists = ExerciseProfile::query()
            ->whereNull('user_id')
            ->where('kind', ExerciseProfileKind::Preset)
            ->where('status', ExerciseProfileStatus::Published)
            ->where('recipe_fingerprint', $profile->recipe()->fingerprint())
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('A published preset already uses those Profile Details.');
        }
    }

    private function publishedPresetSlug(string $name): string
    {
        $base = 'preset-'.Str::slug($name);
        if ($base === 'preset-') {
            $base = 'preset-profile';
        }

        $slug = $base;
        while (
            ExerciseProfile::query()
                ->where('slug_scope', 'system')
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
