<?php

namespace App\Exercises\Services;

use App\Exercises\Enums\ExerciseEquipment;
use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use InvalidArgumentException;
use RuntimeException;

class ExerciseCatalogImporter
{
    /**
     * @return array{muscle_groups: int, created: int, updated: int, skipped: int, pruned: int}
     */
    public function importFromPath(string $path, bool $prune = true): array
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("Catalog file is not readable: {$path}");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("Catalog file is not valid JSON: {$path}");
        }

        return $this->import($decoded, $prune);
    }

    /**
     * @param  array{muscle_groups?: list<array{name: string, slug: string}>, exercises?: list<array{name: string, slug: string, primary: string, secondary?: ?string, equipment?: ?string}>}  $catalog
     * @return array{muscle_groups: int, created: int, updated: int, skipped: int, pruned: int}
     */
    public function import(array $catalog, bool $prune = true): array
    {
        [$groupsCreated, $groupIdsBySlug] = $this->syncMuscleGroups($catalog['muscle_groups'] ?? []);

        // Ensure lookups work for groups that already existed but weren't in this file.
        foreach (MuscleGroup::query()->get(['id', 'slug']) as $existing) {
            $groupIdsBySlug[$existing->slug] ??= $existing->id;
        }

        [$created, $updated, $skipped, $catalogSlugs] = $this->syncExercises(
            $catalog['exercises'] ?? [],
            $groupIdsBySlug,
        );

        return [
            'muscle_groups' => $groupsCreated,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'pruned' => $prune ? $this->pruneSharedNotInCatalog($catalogSlugs) : 0,
        ];
    }

    public static function defaultPath(): string
    {
        return database_path('data/exercises.json');
    }

    /**
     * @param  list<array{name?: mixed, slug?: mixed}>  $groups
     * @return array{0: int, 1: array<string, int<0, max>>}
     */
    private function syncMuscleGroups(array $groups): array
    {
        $groupsCreated = 0;
        /** @var array<string, int<0, max>> $groupIdsBySlug */
        $groupIdsBySlug = [];

        foreach ($groups as $group) {
            $slug = $group['slug'] ?? null;
            $name = $group['name'] ?? null;
            if (! is_string($slug) || $slug === '' || ! is_string($name) || $name === '') {
                continue;
            }

            $model = MuscleGroup::withTrashed()->firstOrNew(['slug' => $slug]);
            if (! $model->exists) {
                $groupsCreated++;
            }
            $model->name = $name;
            if ($model->trashed()) {
                $model->restore();
            }
            $model->save();
            $groupIdsBySlug[$slug] = $model->id;
        }

        return [$groupsCreated, $groupIdsBySlug];
    }

    /**
     * @param  list<array{name?: mixed, slug?: mixed, primary?: mixed, secondary?: mixed, equipment?: mixed}>  $exercises
     * @param  array<string, int<0, max>>  $groupIdsBySlug
     * @return array{0: int, 1: int, 2: int, 3: list<string>}
     */
    private function syncExercises(array $exercises, array $groupIdsBySlug): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        /** @var list<string> $catalogSlugs */
        $catalogSlugs = [];

        foreach ($exercises as $row) {
            $upserted = $this->upsertExerciseRow($row, $groupIdsBySlug);

            if ($upserted === null) {
                $skipped++;

                continue;
            }

            [$slug, $isNew] = $upserted;
            $catalogSlugs[] = $slug;

            if ($isNew) {
                $created++;
            } else {
                $updated++;
            }
        }

        return [$created, $updated, $skipped, $catalogSlugs];
    }

    /**
     * @param  array{name?: mixed, slug?: mixed, primary?: mixed, secondary?: mixed, equipment?: mixed}  $row
     * @param  array<string, int<0, max>>  $groupIdsBySlug
     * @return array{0: string, 1: bool}|null
     */
    private function upsertExerciseRow(array $row, array $groupIdsBySlug): ?array
    {
        $slug = $row['slug'] ?? null;
        $name = $row['name'] ?? null;
        $primarySlug = $row['primary'] ?? null;
        $secondarySlug = $row['secondary'] ?? null;
        $equipment = $this->parseEquipment($row['equipment'] ?? null);

        if (! is_string($slug) || $slug === '' || ! is_string($name) || $name === '' || ! is_string($primarySlug)) {
            return null;
        }

        $primaryId = $groupIdsBySlug[$primarySlug] ?? null;
        if ($primaryId === null || $primaryId < 0) {
            return null;
        }

        $secondaryId = null;
        if (is_string($secondarySlug) && $secondarySlug !== '') {
            $secondaryId = $groupIdsBySlug[$secondarySlug] ?? null;
            if ($secondaryId === null || $secondaryId < 0) {
                return null;
            }
        }

        $exercise = Exercise::withTrashed()->firstOrNew([
            'slug' => $slug,
            'user_id' => null,
        ]);

        $isNew = ! $exercise->exists;
        $exercise->name = $name;
        $exercise->primary_muscle_group_id = $primaryId;
        $exercise->secondary_muscle_group_id = $secondaryId;
        $exercise->equipment = $equipment;
        if ($exercise->trashed()) {
            $exercise->restore();
        }
        $exercise->save();

        return [$slug, $isNew];
    }

    /**
     * Soft-delete shared exercises whose slug is absent from the catalog.
     *
     * @param  list<string>  $catalogSlugs
     */
    private function pruneSharedNotInCatalog(array $catalogSlugs): int
    {
        $query = Exercise::query()->shared();

        if ($catalogSlugs === []) {
            return $query->delete();
        }

        return $query->whereNotIn('slug', $catalogSlugs)->delete();
    }

    private function parseEquipment(mixed $value): ?ExerciseEquipment
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return ExerciseEquipment::tryFrom($value);
    }
}
