<?php

namespace App\ExerciseProfiles\Services;

use App\ExerciseProfiles\Data\AdminExerciseProfileData;
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
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineSetGroup;
use App\Shared\Enums\SetGroupType;
use App\Shared\Support\WarmUpStepSupport;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\LaravelData\DataCollection;

class ExerciseProfileService
{
    public function pageDataFor(User $user): ExerciseProfilePageData
    {
        $defaultId = $this->defaultProfileId($user);
        $custom = $user->exerciseProfiles()
            ->where('status', ExerciseProfileStatus::Published)
            ->orderBy('name')
            ->get();
        $presets = ExerciseProfile::query()
            ->whereNull('user_id')
            ->where('kind', ExerciseProfileKind::Preset)
            ->where('status', ExerciseProfileStatus::Published)
            ->orderBy('name')
            ->get();

        $profiles = $this->orderedProfiles($custom, $presets, $defaultId);
        $staleAssignmentCounts = $this->staleAssignmentCountsForUser($user, $profiles);
        $archived = $user->exerciseProfiles()
            ->where('status', ExerciseProfileStatus::Archived)
            ->orderBy('name')
            ->get();

        return new ExerciseProfilePageData(
            defaultProfileId: $defaultId,
            profiles: ExerciseProfileOptionData::collect(
                $profiles->map(fn (ExerciseProfile $profile): ExerciseProfileOptionData => ExerciseProfileOptionData::fromProfile(
                    $profile,
                    $profile->id === $defaultId,
                    $this->routineReferenceCount($profile),
                    $staleAssignmentCounts[$profile->id] ?? 0,
                )),
                DataCollection::class,
            ),
            archivedProfiles: ExerciseProfileOptionData::collect(
                $archived->map(fn (ExerciseProfile $profile): ExerciseProfileOptionData => ExerciseProfileOptionData::fromProfile(
                    $profile,
                    false,
                    $this->routineReferenceCount($profile),
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
        $custom = $user->exerciseProfiles()
            ->where('status', ExerciseProfileStatus::Published)
            ->orderBy('name')
            ->get();
        $presets = ExerciseProfile::query()
            ->whereNull('user_id')
            ->where('kind', ExerciseProfileKind::Preset)
            ->where('status', ExerciseProfileStatus::Published)
            ->orderBy('name')
            ->get();

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
        $routine->loadMissing([
            'blocks.blockExercises',
            'blocks.sharedExerciseProfile',
        ]);

        $referencedIds = $routine->blocks
            ->flatMap(fn ($block): array => array_merge(
                [$block->shared_exercise_profile_id],
                $block->blockExercises->pluck('exercise_profile_id')->all(),
            ))
            ->push($routine->default_exercise_profile_id)
            ->filter()
            ->unique()
            ->values();

        $profiles = $user->exerciseProfiles()
            ->whereIn('status', [ExerciseProfileStatus::Published, ExerciseProfileStatus::Archived])
            ->where(function ($query) use ($referencedIds): void {
                $query->where('status', ExerciseProfileStatus::Published)
                    ->orWhereIn('id', $referencedIds);
            })
            ->orderBy('name')
            ->get();
        $presets = ExerciseProfile::query()
            ->whereNull('user_id')
            ->where('kind', ExerciseProfileKind::Preset)
            ->where('status', ExerciseProfileStatus::Published)
            ->orderBy('name')
            ->get();
        $defaultId = $this->defaultProfileId($user);

        $options = [];
        foreach ($profiles->concat($presets) as $profile) {
            $options[] = ExerciseProfileOptionData::fromProfile($profile, $profile->id === $defaultId);
        }

        return $options;
    }

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

        if ($this->routineReferenceCount($profile) > 0) {
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

        if ($profile->defaultedByUsers()->exists() || $this->routineReferenceCount($profile) > 0) {
            throw new ExerciseProfileInUseException('This profile is still used by routines. Choose a different profile in the routine editor first.');
        }

        $profile->forceDelete();
    }

    public function syncProfile(User $user, ExerciseProfile $profile): int
    {
        $this->assertEditableCustom($user, $profile);

        return DB::transaction(function () use ($user, $profile): int {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedProfile = ExerciseProfile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
            $recipe = $lockedProfile->recipe();
            $updated = 0;

            $routines = $user->routines()
                ->withTrashed()
                ->with([
                    'blocks.blockExercises',
                    'blocks.setGroups.warmUpSteps',
                ])
                ->get();

            foreach ($routines as $routine) {
                foreach ($routine->blocks as $block) {
                    $sharedAssigned = $block->shared_exercise_profile_id === $lockedProfile->id;
                    if ($sharedAssigned) {
                        $this->materializeSharedRecipe($block, $recipe);
                        $block->forceFill([
                            'shared_profile_fingerprint' => $recipe->sharedFingerprint(),
                        ])->save();
                        $updated++;
                    }

                    foreach ($block->blockExercises as $exercise) {
                        if ($exercise->exercise_profile_id !== $lockedProfile->id) {
                            continue;
                        }

                        $exercise->forceFill([
                            'prescribed_reps' => $recipe->targetReps,
                            'achievement_floor_override' => $recipe->floorOverride,
                            'floor_is_derived' => $recipe->floorOverride === null,
                            'exercise_profile_fingerprint' => $block->is_superset || ! $sharedAssigned
                                ? $recipe->exerciseFingerprint()
                                : $recipe->fingerprint(),
                        ])->save();
                        $updated++;
                    }
                }
            }

            return $updated;
        });
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

    private function routineReferenceCount(ExerciseProfile $profile): int
    {
        $routineIds = $profile->defaultedByRoutines()->pluck('id');

        $routineIds = $routineIds->merge(
            Routine::query()
                ->whereHas('blocks', fn ($query) => $query->where('shared_exercise_profile_id', $profile->id))
                ->pluck('id'),
        );

        $routineIds = $routineIds->merge(
            Routine::query()
                ->whereHas('blocks.blockExercises', fn ($query) => $query->where('exercise_profile_id', $profile->id))
                ->pluck('id'),
        );

        return $routineIds->unique()->count();
    }

    /**
     * @param  Collection<int, ExerciseProfile>  $profiles
     * @return array<int, int>
     */
    private function staleAssignmentCountsForUser(User $user, Collection $profiles): array
    {
        $customProfiles = $profiles
            ->filter(fn (ExerciseProfile $profile): bool => $profile->isCustom())
            ->keyBy('id');

        /** @var array<int, int> $counts */
        $counts = $customProfiles
            ->map(fn (): int => 0)
            ->all();

        if ($counts === []) {
            return $counts;
        }

        /** @var array<int, ExerciseProfileRecipe> $recipes */
        $recipes = $customProfiles
            ->map(fn (ExerciseProfile $profile): ExerciseProfileRecipe => $profile->recipe())
            ->all();

        $routines = $user->routines()
            ->with(['blocks.blockExercises'])
            ->get();

        foreach ($routines as $routine) {
            foreach ($routine->blocks as $block) {
                $sharedProfileId = $block->shared_exercise_profile_id;
                $sharedAssignedToTrackedProfile = $sharedProfileId !== null && isset($counts[$sharedProfileId]);

                if ($sharedAssignedToTrackedProfile) {
                    $sharedRecipe = $recipes[$sharedProfileId];
                    if ($block->shared_profile_fingerprint !== $sharedRecipe->sharedFingerprint()) {
                        $counts[$sharedProfileId]++;
                    }
                }

                foreach ($block->blockExercises as $exercise) {
                    $exerciseProfileId = $exercise->exercise_profile_id;
                    if ($exerciseProfileId === null || ! isset($counts[$exerciseProfileId])) {
                        continue;
                    }

                    $exerciseRecipe = $recipes[$exerciseProfileId];
                    $expectedFingerprint = $block->is_superset || ! $sharedAssignedToTrackedProfile
                        ? $exerciseRecipe->exerciseFingerprint()
                        : $exerciseRecipe->fingerprint();

                    if ($exercise->exercise_profile_fingerprint !== $expectedFingerprint) {
                        $counts[$exerciseProfileId]++;
                    }
                }
            }
        }

        return $counts;
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
        $normalized = mb_strtolower(trim($name));
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
        $name = trim($name);
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

    private function materializeSharedRecipe(RoutineBlock $block, ExerciseProfileRecipe $recipe): void
    {
        $working = $block->setGroups->firstWhere('type', SetGroupType::Working);
        if ($working instanceof RoutineSetGroup) {
            $working->forceFill(['rest_seconds' => $recipe->workingRestSeconds])->save();
        }

        $warmUp = $block->setGroups->firstWhere('type', SetGroupType::WarmUp);
        if (! $warmUp instanceof RoutineSetGroup) {
            return;
        }

        $existingSetupFlags = $warmUp->warmUpSteps
            ->map(static fn ($step): bool => (bool) $step->has_setup_after)
            ->values()
            ->all();

        $warmUp->warmUpSteps()->delete();
        foreach ($recipe->warmUpSteps as $index => $step) {
            $normalized = WarmUpStepSupport::normalize($step);
            if ($normalized === null) {
                continue;
            }

            $warmUp->warmUpSteps()->create([
                'position' => $index + 1,
                'weight_mode' => $normalized['mode'],
                'percent_of_working' => $normalized['percent'],
                'reps' => $normalized['reps'],
                'has_setup_after' => $existingSetupFlags[$index] ?? false,
            ]);
        }
        $warmUp->forceFill(['set_count' => count($recipe->warmUpSteps)])->save();
        if ($recipe->warmUpSteps === []) {
            $block->forceFill(['has_setup_after_warm_up' => false])->save();
        }
    }

    private function defaultProfileId(User $user): ?int
    {
        if (! array_key_exists('default_exercise_profile_id', $user->getAttributes())) {
            return null;
        }

        return $user->default_exercise_profile_id;
    }
}
