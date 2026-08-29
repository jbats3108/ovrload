<?php

namespace Database\Seeders;

use App\ExerciseProfiles\Models\ExerciseProfile;
use App\Exercises\Enums\ExerciseEquipment;
use App\Exercises\Models\Exercise;
use App\Routines\Models\Routine;
use App\Routines\Models\RoutineBlock;
use App\Routines\Models\RoutineBlockExercise;
use App\Routines\Models\RoutineDropsetSegment;
use App\Routines\Models\RoutineSetGroup;
use App\Routines\Models\RoutineWarmUpStep;
use App\Shared\Enums\SetGroupType;
use App\Shared\Support\Weight;
use App\Users\Enums\WarmUpDefaultsScope;
use App\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo routines for local Play / editor testing (user1@test.com).
 *
 * | Routine | Covers |
 * |---|---|
 * | Barbell Strength | Barbell + plates, WU first block only, setup after WU |
 * | Dumbbell Accessories | Dumbbell + bodyweight dip @ 28.75, WU on every block |
 * | Superset Pump | Supersets, WU on some blocks only, mixed equipment |
 * | Dropset Finishers | Dropsets mixed with single sets, barbell + DB |
 */
class RoutineSeeder extends Seeder
{
    /** @var list<string> */
    public const DEMO_NAMES = [
        'Barbell Strength',
        'Dumbbell Accessories',
        'Superset Pump',
        'Dropset Finishers',
    ];

    /** @var array<string, ExerciseProfile> */
    private array $profiles = [];

    /** @var list<array{percent: int, reps: int}> */
    private const DEFAULT_WARM_UPS = [
        ['percent' => 50, 'reps' => 5],
        ['percent' => 75, 'reps' => 3],
        ['percent' => 90, 'reps' => 1],
    ];

    public function run(): void
    {
        $user = User::query()->where('email', 'user1@test.com')->first()
            ?? User::query()->whereHas('roles', fn ($q) => $q->where('name', 'user'))->first();

        if ($user === null) {
            return;
        }

        $this->loadProfiles();
        $strength = $this->profile('preset-strength');

        $defaults = [
            'warm_up_defaults_scope' => WarmUpDefaultsScope::FirstBlock,
            'warm_up_steps_default' => self::DEFAULT_WARM_UPS,
        ];
        if ($strength !== null) {
            $defaults['default_exercise_profile_id'] = $strength->id;
            $defaults['achievement_floor_default'] = $strength->resolvedFloor();
            $defaults['progression_target_default'] = $strength->target_reps;
        }
        $user->forceFill($defaults)->save();

        $this->replaceDemoRoutines($user);

        $this->seedBarbellStrength($user);
        $this->seedDumbbellAccessories($user);
        $this->seedSupersetPump($user);
        $this->seedDropsetFinishers($user);
    }

    private function replaceDemoRoutines(User $user): void
    {
        $routines = Routine::query()
            ->where('user_id', $user->id)
            ->whereIn('name', self::DEMO_NAMES)
            ->get();

        foreach ($routines as $routine) {
            if ($routine->workouts()->exists()) {
                $routine->update(['name' => $routine->name.' (archived)']);
                $routine->delete();

                continue;
            }

            $routine->forceDelete();
        }

        // Hollow leftovers from the old name-only seeder.
        Routine::query()
            ->where('user_id', $user->id)
            ->whereDoesntHave('blocks')
            ->whereDoesntHave('workouts')
            ->forceDelete();
    }

    private function loadProfiles(): void
    {
        foreach (ExerciseProfile::query()->get() as $profile) {
            if ($profile->slug !== null) {
                $this->profiles[$profile->slug] = $profile;
            }
        }
    }

    private function profile(?string $slug): ?ExerciseProfile
    {
        return $slug === null ? null : ($this->profiles[$slug] ?? null);
    }

    private function seedBarbellStrength(User $user): void
    {
        $routine = $this->createRoutine($user, 'Barbell Strength', 'preset-strength');

        // Block 1 — only warm-up in this routine; setup before working; plate guide.
        $this->addBlock($routine, position: 1, exercises: [
            ['name' => 'Barbell Bench Press - Medium Grip', 'equipment' => ExerciseEquipment::Barbell, 'kg' => 80, 'reps' => 5, 'profile' => 'preset-strength'],
        ], workingSets: 3, workingRest: 180, warmUps: self::DEFAULT_WARM_UPS, setupAfterWarmUp: true, sharedProfileSlug: 'preset-strength');

        $this->addBlock($routine, position: 2, exercises: [
            ['name' => 'Bent Over Barbell Row', 'equipment' => ExerciseEquipment::Barbell, 'kg' => 70, 'reps' => 6, 'profile' => 'preset-strength'],
        ], workingSets: 3, workingRest: 150, warmUps: [], setupAfter: true);

        $this->addBlock($routine, position: 3, exercises: [
            ['name' => 'Barbell Squat', 'equipment' => ExerciseEquipment::Barbell, 'kg' => 100, 'reps' => 5, 'profile' => 'preset-strength'],
        ], workingSets: 3, workingRest: 180, warmUps: []);
    }

    private function seedDumbbellAccessories(User $user): void
    {
        $routine = $this->createRoutine($user, 'Dumbbell Accessories', 'preset-hypertrophy');
        $wu = self::DEFAULT_WARM_UPS;

        $this->addBlock($routine, position: 1, exercises: [
            ['name' => 'Dumbbell Bench Press', 'equipment' => ExerciseEquipment::Dumbbell, 'kg' => 28, 'reps' => 8, 'profile' => 'preset-hypertrophy'],
        ], workingSets: 3, workingRest: 90, warmUps: $wu, sharedProfileSlug: 'preset-hypertrophy');

        $this->addBlock($routine, position: 2, exercises: [
            ['name' => 'Arnold Dumbbell Press', 'equipment' => ExerciseEquipment::Dumbbell, 'kg' => 16, 'reps' => 10, 'profile' => 'preset-hypertrophy'],
        ], workingSets: 3, workingRest: 90, warmUps: $wu, sharedProfileSlug: 'preset-hypertrophy');

        $this->addBlock($routine, position: 3, exercises: [
            ['name' => 'Alternate Hammer Curl', 'equipment' => ExerciseEquipment::Dumbbell, 'kg' => 14, 'reps' => 12, 'profile' => 'preset-hypertrophy'],
        ], workingSets: 3, workingRest: 90, warmUps: $wu, sharedProfileSlug: 'preset-hypertrophy');

        // Fractional load (no plate guide) — gym-test 28.75 dip belt.
        $this->addBlock($routine, position: 4, exercises: [
            ['name' => 'Bench Dips', 'equipment' => ExerciseEquipment::BodyOnly, 'kg' => 28.75, 'reps' => 8, 'profile' => 'preset-hypertrophy'],
        ], workingSets: 3, workingRest: 90, warmUps: $wu, setupAfter: true, sharedProfileSlug: 'preset-hypertrophy');
    }

    private function seedSupersetPump(User $user): void
    {
        $routine = $this->createRoutine($user, 'Superset Pump', 'preset-strength');

        // WU on first block only of the three — "some" blocks.
        $this->addBlock($routine, position: 1, exercises: [
            ['name' => 'Close-Grip Barbell Bench Press', 'equipment' => ExerciseEquipment::Barbell, 'kg' => 60, 'reps' => 8, 'profile' => 'preset-strength'],
            ['name' => 'Barbell Curl', 'equipment' => ExerciseEquipment::Barbell, 'kg' => 30, 'reps' => 10, 'profile' => 'power-builder'],
        ], workingSets: 3, workingRest: 120, warmUps: self::DEFAULT_WARM_UPS, setupAfterWarmUp: true, isSuperset: true, sharedProfileSlug: 'preset-strength');

        $this->addBlock($routine, position: 2, exercises: [
            ['name' => 'Arnold Dumbbell Press', 'equipment' => ExerciseEquipment::Dumbbell, 'kg' => 14, 'reps' => 10, 'profile' => 'preset-hypertrophy'],
            ['name' => 'Alternate Hammer Curl', 'equipment' => ExerciseEquipment::Dumbbell, 'kg' => 12, 'reps' => 12, 'profile' => 'accessory-volume'],
        ], workingSets: 3, workingRest: 90, warmUps: [], isSuperset: true);

        $this->addBlock($routine, position: 3, exercises: [
            ['name' => 'Bent Over Two-Dumbbell Row', 'equipment' => ExerciseEquipment::Dumbbell, 'kg' => 22, 'reps' => 10, 'profile' => 'preset-endurance'],
        ], workingSets: 3, workingRest: 90, warmUps: [
            ['percent' => 50, 'reps' => 8],
            ['percent' => 70, 'reps' => 5],
        ], sharedProfileSlug: 'preset-endurance');
    }

    private function seedDropsetFinishers(User $user): void
    {
        $routine = $this->createRoutine($user, 'Dropset Finishers', 'preset-strength');

        // Set indexes 0–1 single, index 2 dropset.
        $this->addBlock($routine, position: 1, exercises: [
            ['name' => 'Barbell Curl', 'equipment' => ExerciseEquipment::Barbell, 'kg' => 35, 'reps' => 10, 'profile' => 'preset-strength'],
        ], workingSets: 3, workingRest: 90, warmUps: self::DEFAULT_WARM_UPS, dropsets: [
            2 => [35, 25, 15],
        ], sharedProfileSlug: 'preset-strength');

        // Set index 0 single, index 1 dropset (mixed in one group).
        $this->addBlock($routine, position: 2, exercises: [
            ['name' => 'Dumbbell Bench Press', 'equipment' => ExerciseEquipment::Dumbbell, 'kg' => 26, 'reps' => 10, 'profile' => 'accessory-volume'],
        ], workingSets: 2, workingRest: 90, warmUps: [], dropsets: [
            1 => [26, 20, 14],
        ]);

        $this->addBlock($routine, position: 3, exercises: [
            ['name' => 'Barbell Shoulder Press', 'equipment' => ExerciseEquipment::Barbell, 'kg' => 45, 'reps' => 8, 'profile' => 'preset-endurance'],
        ], workingSets: 3, workingRest: 120, warmUps: [], setupAfter: true);
    }

    private function createRoutine(User $user, string $name, ?string $defaultProfileSlug = null): Routine
    {
        return Routine::create([
            'user_id' => $user->id,
            'name' => $name,
            'default_exercise_profile_id' => $this->profile($defaultProfileSlug)?->id,
            'deload_weight_factor' => 0.5,
            'deload_reps_factor' => 0.5,
            'deload_every_n' => 3,
        ]);
    }

    /**
     * @param  list<array{name: string, equipment: ExerciseEquipment, kg: float|int, reps: int, profile?: string}>  $exercises
     * @param  list<array{percent: int, reps: int}>  $warmUps
     * @param  array<int, list<float|int>>  $dropsets  set_index => kg weights
     */
    private function addBlock(
        Routine $routine,
        int $position,
        array $exercises,
        int $workingSets,
        int $workingRest,
        array $warmUps = [],
        array $dropsets = [],
        bool $isSuperset = false,
        bool $setupAfter = false,
        bool $setupAfterWarmUp = false,
        ?string $sharedProfileSlug = null,
    ): void {
        if ($isSuperset && count($exercises) !== 2) {
            throw new RuntimeException('Superset blocks require exactly two exercises.');
        }

        if ($isSuperset && $dropsets !== []) {
            throw new RuntimeException('Dropsets are not seeded on supersets.');
        }

        $sharedProfile = $this->profile($sharedProfileSlug);
        if ($sharedProfile !== null) {
            $workingRest = $sharedProfile->working_rest_seconds;
            $warmUps = $sharedProfile->warmUpStepList();
        }

        DB::transaction(function () use (
            $routine,
            $position,
            $exercises,
            $workingSets,
            $workingRest,
            $warmUps,
            $dropsets,
            $isSuperset,
            $setupAfter,
            $setupAfterWarmUp,
            $sharedProfile,
        ): void {
            $block = RoutineBlock::create([
                'routine_id' => $routine->id,
                'position' => $position,
                'is_superset' => $isSuperset,
                'has_setup_after' => $setupAfter,
                'has_setup_after_warm_up' => $setupAfterWarmUp,
                'shared_exercise_profile_id' => $sharedProfile?->id,
                'shared_profile_fingerprint' => $sharedProfile?->recipe()->sharedFingerprint(),
            ]);

            foreach (array_values($exercises) as $index => $exercise) {
                $profile = $this->profile($exercise['profile'] ?? $sharedProfileSlug);
                $usesExerciseFingerprint = $isSuperset || $sharedProfile === null;
                RoutineBlockExercise::create([
                    'routine_block_id' => $block->id,
                    'exercise_id' => $this->resolveExercise($exercise['name'], $exercise['equipment'])->id,
                    'position' => $index + 1,
                    'working_weight_g' => Weight::kgToGrams($exercise['kg']),
                    'prescribed_reps' => $profile?->target_reps ?? $exercise['reps'],
                    'achievement_floor_override' => $profile?->floor_override,
                    'floor_is_derived' => $profile === null ? null : $profile->floor_override === null,
                    'exercise_profile_id' => $profile?->id,
                    'exercise_profile_fingerprint' => $profile === null
                        ? null
                        : ($usesExerciseFingerprint
                            ? $profile->recipe()->exerciseFingerprint()
                            : $profile->recipe()->fingerprint()),
                ]);
            }

            $working = RoutineSetGroup::create([
                'routine_block_id' => $block->id,
                'type' => SetGroupType::Working,
                'set_count' => $workingSets,
                'rest_seconds' => $workingRest,
            ]);

            foreach ($dropsets as $setIndex => $segmentKgs) {
                foreach (array_values($segmentKgs) as $segIndex => $kg) {
                    RoutineDropsetSegment::create([
                        'routine_set_group_id' => $working->id,
                        'set_index' => $setIndex,
                        'position' => $segIndex + 1,
                        'weight_g' => Weight::kgToGrams($kg),
                    ]);
                }
            }

            $warmUpGroup = RoutineSetGroup::create([
                'routine_block_id' => $block->id,
                'type' => SetGroupType::WarmUp,
                'set_count' => count($warmUps),
                'rest_seconds' => $warmUps === [] ? 60 : 60,
            ]);

            foreach (array_values($warmUps) as $stepIndex => $step) {
                RoutineWarmUpStep::create([
                    'routine_set_group_id' => $warmUpGroup->id,
                    'position' => $stepIndex + 1,
                    'percent_of_working' => $step['percent'],
                    'reps' => $step['reps'],
                ]);
            }
        });
    }

    private function resolveExercise(string $name, ExerciseEquipment $equipment): Exercise
    {
        $exercise = Exercise::query()->shared()->where('name', $name)->first();

        if ($exercise !== null) {
            if ($exercise->equipment !== $equipment) {
                $exercise->equipment = $equipment;
                $exercise->save();
            }

            return $exercise;
        }

        throw new RuntimeException(
            "Shared exercise [{$name}] not found. Run ExerciseSeeder (catalog import) before RoutineSeeder."
        );
    }
}
