<?php

namespace Database\Seeders;

use App\Exercises\Enums\ExerciseEquipment;
use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use Illuminate\Database\Seeder;
use RuntimeException;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = $this->catalog();
        $groupIdsBySlug = $this->seedMuscleGroups($catalog['muscle_groups'] ?? []);

        foreach (MuscleGroup::query()->get(['id', 'slug']) as $existing) {
            $groupIdsBySlug[$existing->slug] ??= $existing->id;
        }

        foreach ($catalog['exercises'] ?? [] as $row) {
            $this->seedExercise($row, $groupIdsBySlug);
        }
    }

    public static function defaultPath(): string
    {
        return database_path('data/exercises.json');
    }

    /**
     * @return array{
     *     muscle_groups?: list<array{name?: mixed, slug?: mixed}>,
     *     exercises?: list<array{name?: mixed, slug?: mixed, primary?: mixed, secondary?: mixed, equipment?: mixed}>
     * }
     */
    private function catalog(): array
    {
        $path = self::defaultPath();
        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Exercise catalog is not valid JSON: {$path}");
        }

        return $decoded;
    }

    /**
     * @param  list<array{name?: mixed, slug?: mixed}>  $groups
     * @return array<string, int>
     */
    private function seedMuscleGroups(array $groups): array
    {
        /** @var array<string, int> $groupIdsBySlug */
        $groupIdsBySlug = [];

        foreach ($groups as $group) {
            $slug = $group['slug'] ?? null;
            $name = $group['name'] ?? null;
            if (! is_string($slug) || $slug === '' || ! is_string($name) || $name === '') {
                continue;
            }

            $model = MuscleGroup::withTrashed()->firstOrNew(['slug' => $slug]);
            $model->name = $name;
            if ($model->trashed()) {
                $model->restore();
            }
            $model->save();
            $groupIdsBySlug[$slug] = $model->id;
        }

        return $groupIdsBySlug;
    }

    /**
     * @param  array{name?: mixed, slug?: mixed, primary?: mixed, secondary?: mixed, equipment?: mixed}  $row
     * @param  array<string, int>  $groupIdsBySlug
     */
    private function seedExercise(array $row, array $groupIdsBySlug): void
    {
        $slug = $row['slug'] ?? null;
        $name = $row['name'] ?? null;
        $primarySlug = $row['primary'] ?? null;
        $secondarySlug = $row['secondary'] ?? null;

        if (! is_string($slug) || $slug === '' || ! is_string($name) || $name === '' || ! is_string($primarySlug)) {
            return;
        }

        $primaryId = $groupIdsBySlug[$primarySlug] ?? null;
        if ($primaryId === null) {
            return;
        }

        $secondaryId = null;
        if (is_string($secondarySlug) && $secondarySlug !== '') {
            $secondaryId = $groupIdsBySlug[$secondarySlug] ?? null;
            if ($secondaryId === null) {
                return;
            }
        }

        $equipment = null;
        $equipmentValue = $row['equipment'] ?? null;
        if (is_string($equipmentValue) && $equipmentValue !== '') {
            $equipment = ExerciseEquipment::tryFrom($equipmentValue);
        }

        $exercise = Exercise::withTrashed()->firstOrNew([
            'slug' => $slug,
            'user_id' => null,
        ]);
        $exercise->name = $name;
        $exercise->primary_muscle_group_id = $primaryId;
        $exercise->secondary_muscle_group_id = $secondaryId;
        $exercise->equipment = $equipment;
        if ($exercise->trashed()) {
            $exercise->restore();
        }
        $exercise->save();
    }
}
