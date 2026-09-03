<?php

namespace App\ExerciseProfiles\Services;

use App\ExerciseProfiles\Data\AdminExerciseProfilePageData;
use App\ExerciseProfiles\Data\ExerciseProfileOptionData;
use App\ExerciseProfiles\Data\ExerciseProfilePageData;
use App\ExerciseProfiles\Data\ExerciseProfileWarmUpStepData;
use App\ExerciseProfiles\Data\SaveExerciseProfileData;
use App\ExerciseProfiles\Enums\ExerciseProfileKind;
use App\ExerciseProfiles\Enums\ExerciseProfileStatus;
use App\ExerciseProfiles\Exceptions\ExerciseProfileInUseException;
use App\ExerciseProfiles\Exceptions\ExerciseProfileNotEditableException;
use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Routines\Models\Routine;
use App\Shared\Support\WarmUpStepSupport;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\LaravelData\DataCollection;

class ExerciseProfileService
{
    public function __construct(
        private readonly ExerciseProfilePresetService $presets,
        private readonly ExerciseProfileAssignmentService $assignments,
    ) {}

    public function pageDataFor(User $user): ExerciseProfilePageData
    {
        $defaultId = $this->defaultProfileId($user);
        $custom = $this->publishedCustomProfilesFor($user);
        $presets = $this->publishedPresetProfiles();

        $profiles = $this->orderedProfiles($custom, $presets, $defaultId);
        $staleAssignmentCounts = $this->assignments->staleAssignmentCountsForUser($user, $profiles);
        $archived = $user->exerciseProfiles()
            ->where('status', ExerciseProfileStatus::Archived)
            ->orderBy('name')
            ->get();
        $assignedById = $this->assignments->assignedRoutinesByProfileId($user, $profiles->concat($archived));

        return new ExerciseProfilePageData(
            defaultProfileId: $defaultId,
            profiles: ExerciseProfileOptionData::collect(
                $profiles->map(fn (ExerciseProfile $profile): ExerciseProfileOptionData => ExerciseProfileOptionData::fromProfile(
                    $profile,
                    $profile->id === $defaultId,
                    $staleAssignmentCounts[$profile->id] ?? 0,
                    $assignedById[$profile->id] ?? [],
                )),
                DataCollection::class,
            ),
            archivedProfiles: ExerciseProfileOptionData::collect(
                $archived->map(fn (ExerciseProfile $profile): ExerciseProfileOptionData => ExerciseProfileOptionData::fromProfile(
                    $profile,
                    false,
                    0,
                    $assignedById[$profile->id] ?? [],
                )),
                DataCollection::class,
            ),
        );
    }

    /**
     * @return list<ExerciseProfileOptionData>
     */
    public function optionsForUser(User $user, ?int $defaultId = null): array
    {
        $custom = $this->publishedCustomProfilesFor($user);
        $presets = $this->publishedPresetProfiles();

        $options = [];
        foreach ($this->orderedProfiles($custom, $presets, $defaultId) as $profile) {
            $options[] = ExerciseProfileOptionData::fromProfile($profile, $profile->id === $defaultId);
        }

        return $options;
    }

    /**
     * @return list<ExerciseProfileOptionData>
     */
    public function optionsForRoutineEditor(User $user, Routine $routine): array
    {
        $routine->loadMissing(['blocks.blockExercises']);

        $referencedIds = $this->assignments->profileIdsReferencedBy($routine);
        $profiles = $this->selectableCustomProfilesFor($user, $referencedIds);
        $presets = $this->publishedPresetProfiles();
        $defaultId = $this->defaultProfileId($user);

        $options = [];
        foreach ($profiles->concat($presets) as $profile) {
            $options[] = ExerciseProfileOptionData::fromProfile($profile, $profile->id === $defaultId);
        }

        return $options;
    }

    public function adminPageData(): AdminExerciseProfilePageData
    {
        return $this->presets->adminPageData();
    }

    public function createPreset(User $admin, SaveExerciseProfileData $data): ExerciseProfile
    {
        return $this->presets->createPreset($admin, $data);
    }

    public function updatePresetDraft(User $admin, ExerciseProfile $profile, SaveExerciseProfileData $data): ExerciseProfile
    {
        return $this->presets->updatePresetDraft($admin, $profile, $data);
    }

    public function deletePresetDraft(User $admin, ExerciseProfile $profile): void
    {
        $this->presets->deletePresetDraft($admin, $profile);
    }

    public function publishPreset(User $admin, ExerciseProfile $profile): ExerciseProfile
    {
        return $this->presets->publishPreset($admin, $profile);
    }

    public function createCustom(User $user, SaveExerciseProfileData $data): ExerciseProfile
    {
        return DB::transaction(function () use ($user, $data): ExerciseProfile {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertNameAvailable($user, $data->name);
            $recipe = $this->recipeFromData($data);
            $slug = $this->newCustomSlug($user, $data->name);

            return ExerciseProfile::create([
                'user_id' => $user->id,
                'created_by_user_id' => null,
                'kind' => ExerciseProfileKind::Custom,
                'status' => ExerciseProfileStatus::Published,
                'name' => trim($data->name),
                'slug' => $slug,
                'slug_scope' => 'user-'.$user->id,
                'target_reps' => $recipe->targetReps,
                'floor_override' => $recipe->floorOverride,
                'working_rest_seconds' => $recipe->workingRestSeconds,
                'warm_up_steps' => $recipe->warmUpSteps,
                'recipe_fingerprint' => $recipe->fingerprint(),
                'published_at' => now(),
            ]);
        });
    }

    public function updateCustom(User $user, ExerciseProfile $profile, SaveExerciseProfileData $data): ExerciseProfile
    {
        $this->assertEditableCustom($user, $profile);

        return DB::transaction(function () use ($user, $profile, $data): ExerciseProfile {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertNameAvailable($user, $data->name, $profile);
            $recipe = $this->recipeFromData($data);

            $profile->update([
                'name' => trim($data->name),
                'target_reps' => $recipe->targetReps,
                'floor_override' => $recipe->floorOverride,
                'working_rest_seconds' => $recipe->workingRestSeconds,
                'warm_up_steps' => $recipe->warmUpSteps,
                'recipe_fingerprint' => $recipe->fingerprint(),
            ]);

            return $profile->fresh() ?? $profile;
        });
    }

    public function setDefault(User $user, ExerciseProfile $profile): void
    {
        $this->assertSelectable($user, $profile);
        $user->forceFill(['default_exercise_profile_id' => $profile->id])->save();
    }

    public function archive(User $user, ExerciseProfile $profile): void
    {
        $this->assertOwnedCustom($user, $profile);
        if ($this->defaultProfileId($user) === $profile->id) {
            throw new InvalidArgumentException('The user default profile cannot be archived.');
        }

        if ($this->assignments->liveRoutineCountFor($user, $profile) > 0) {
            throw new InvalidArgumentException('This profile is still used by routines. Choose a different profile in the routine editor first.');
        }

        $profile->update(['status' => ExerciseProfileStatus::Archived]);
    }

    public function restore(User $user, ExerciseProfile $profile): void
    {
        $this->assertOwnedCustom($user, $profile);
        $this->assertNameAvailable($user, $profile->name, $profile);
        $profile->update(['status' => ExerciseProfileStatus::Published]);
    }

    public function delete(User $user, ExerciseProfile $profile): void
    {
        $this->assertOwnedCustom($user, $profile);

        if ($profile->defaultedByUsers()->exists() || $this->assignments->liveRoutineCountFor($user, $profile) > 0) {
            throw new ExerciseProfileInUseException('This profile is still used by routines. Choose a different profile in the routine editor first.');
        }

        $profile->forceDelete();
    }

    public function syncProfile(User $user, ExerciseProfile $profile): int
    {
        return $this->assignments->syncProfile($user, $profile);
    }

    /**
     * @param  Collection<int, ExerciseProfile>  $custom
     * @param  Collection<int, ExerciseProfile>  $presets
     * @return Collection<int, ExerciseProfile>
     */
    private function orderedProfiles(Collection $custom, Collection $presets, ?int $defaultId): Collection
    {
        $all = $custom->concat($presets);
        $default = $all->firstWhere('id', $defaultId);
        $remaining = $all->reject(fn (ExerciseProfile $profile): bool => $profile->id === $defaultId);

        return new Collection(
            array_values(array_merge(
                $default === null ? [] : [$default],
                $remaining
                    ->filter(fn (ExerciseProfile $profile): bool => $profile->isCustom())
                    ->values()
                    ->all(),
                $remaining
                    ->filter(fn (ExerciseProfile $profile): bool => $profile->isPreset())
                    ->values()
                    ->all(),
            )),
        );
    }

    /**
     * @return Collection<int, ExerciseProfile>
     */
    private function publishedCustomProfilesFor(User $user): Collection
    {
        return $user->exerciseProfiles()
            ->where('status', ExerciseProfileStatus::Published)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, ExerciseProfile>
     */
    private function publishedPresetProfiles(): Collection
    {
        return ExerciseProfile::query()
            ->whereNull('user_id')
            ->where('kind', ExerciseProfileKind::Preset)
            ->where('status', ExerciseProfileStatus::Published)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<int>  $includeArchivedIds
     * @return Collection<int, ExerciseProfile>
     */
    private function selectableCustomProfilesFor(User $user, array $includeArchivedIds): Collection
    {
        if ($includeArchivedIds === []) {
            return $this->publishedCustomProfilesFor($user);
        }

        return $user->exerciseProfiles()
            ->whereIn('status', [ExerciseProfileStatus::Published, ExerciseProfileStatus::Archived])
            ->where(function ($query) use ($includeArchivedIds): void {
                $query->where('status', ExerciseProfileStatus::Published)
                    ->orWhereIn('id', $includeArchivedIds);
            })
            ->orderBy('name')
            ->get();
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

    public function assertSelectable(User $user, ExerciseProfile $profile): void
    {
        $this->assertAssignable($user, $profile);

        if (! $profile->isSelectable()) {
            throw new ExerciseProfileNotEditableException('That profile is not available.');
        }
    }

    public function assertAssignable(User $user, ExerciseProfile $profile): void
    {
        if (
            ($profile->isCustom() && $profile->user_id !== $user->id)
            || ($profile->isPreset() && $profile->user_id !== null)
            || ($profile->status !== ExerciseProfileStatus::Published && $profile->status !== ExerciseProfileStatus::Archived)
        ) {
            throw new ExerciseProfileNotEditableException('That profile is not available.');
        }
    }

    private function assertEditableCustom(User $user, ExerciseProfile $profile): void
    {
        $this->assertOwnedCustom($user, $profile);

        if ($profile->isArchived()) {
            throw new ExerciseProfileNotEditableException('Restore the profile before editing it.');
        }
    }

    private function assertOwnedCustom(User $user, ExerciseProfile $profile): void
    {
        if (! $profile->isCustom() || $profile->user_id !== $user->id) {
            throw new ExerciseProfileNotEditableException('That profile is not yours to change.');
        }
    }

    private function assertNameAvailable(User $user, string $name, ?ExerciseProfile $except = null): void
    {
        $normalized = $name
            |> trim(...)
            |> mb_strtolower(...);
        if ($normalized === '') {
            throw new InvalidArgumentException('Profile name is required.');
        }

        if (str_starts_with($normalized, 'ovrload ')) {
            throw new InvalidArgumentException('Custom profiles cannot use the OVRLOAD prefix.');
        }

        $query = $user->exerciseProfiles()
            ->whereRaw('LOWER(name) = ?', [$normalized]);
        if ($except !== null) {
            $query->whereKeyNot($except->id);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('You already have a profile with that name.');
        }
    }

    private function newCustomSlug(User $user, string $name): string
    {
        $base = Str::slug($name);
        if ($base === '' || str_starts_with($base, 'preset-')) {
            $base = 'profile';
        }

        $slug = $base;
        $suffix = 2;
        while ($user->exerciseProfiles()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function defaultProfileId(User $user): ?int
    {
        if (! array_key_exists('default_exercise_profile_id', $user->getAttributes())) {
            return null;
        }

        return $user->default_exercise_profile_id;
    }
}
