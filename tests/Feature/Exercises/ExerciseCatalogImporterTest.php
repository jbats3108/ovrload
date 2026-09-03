<?php

namespace Tests\Feature\Exercises;

use App\Exercises\Models\Exercise;
use App\Exercises\Services\ExerciseCatalogImporter;
use App\MuscleGroups\Models\MuscleGroup;
use App\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExerciseCatalogImporterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_the_default_shared_catalog(): void
    {
        $result = (new ExerciseCatalogImporter)->importFromPath(ExerciseCatalogImporter::defaultPath());

        $this->assertSame(0, $result['skipped']);
        $this->assertGreaterThan(50, $result['created']);
        $this->assertLessThanOrEqual(220, $result['created']);
        $this->assertSame(0, $result['updated']);
        $this->assertSame(0, $result['pruned']);
        $this->assertGreaterThan(8, MuscleGroup::count());

        $sharedCount = Exercise::shared()->count();
        $this->assertGreaterThanOrEqual(150, $sharedCount);
        $this->assertLessThanOrEqual(220, $sharedCount);
        $this->assertSame($result['created'], $sharedCount);

        $this->assertDatabaseHas('exercises', [
            'slug' => 'barbell-deadlift',
            'user_id' => null,
            'equipment' => 'barbell',
        ]);
        $this->assertDatabaseHas('exercises', [
            'slug' => 'arnold-dumbbell-press',
            'equipment' => 'dumbbell',
        ]);
        $this->assertDatabaseHas('exercises', [
            'slug' => 'bench-press-powerlifting',
            'equipment' => 'barbell',
        ]);
        $this->assertDatabaseHas('exercises', [
            'slug' => 'barbell-bench-press',
            'name' => 'Barbell Bench Press',
            'equipment' => 'barbell',
        ]);
    }

    #[Test]
    public function it_is_idempotent_on_reimport(): void
    {
        $importer = new ExerciseCatalogImporter;
        $importer->importFromPath(ExerciseCatalogImporter::defaultPath());
        $count = Exercise::shared()->count();

        $second = $importer->importFromPath(ExerciseCatalogImporter::defaultPath());

        $this->assertSame(0, $second['created']);
        $this->assertSame($count, $second['updated']);
        $this->assertSame(0, $second['pruned']);
        $this->assertSame($count, Exercise::shared()->count());
    }

    #[Test]
    public function it_soft_deletes_shared_exercises_missing_from_the_catalog(): void
    {
        $importer = new ExerciseCatalogImporter;
        $importer->importFromPath(ExerciseCatalogImporter::defaultPath());

        $orphan = Exercise::factory()->create([
            'user_id' => null,
            'slug' => 'orphan-press',
            'name' => 'Orphan Press',
        ]);
        $custom = Exercise::factory()->create([
            'user_id' => User::factory()->create()->id,
            'slug' => 'my-custom-press',
            'name' => 'My Custom Press',
        ]);

        $path = storage_path('framework/testing/catalog-prune.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'muscle_groups' => [
                ['name' => 'Chest', 'slug' => 'chest'],
            ],
            'exercises' => [
                [
                    'name' => 'Keep Press',
                    'slug' => 'keep-press',
                    'primary' => 'chest',
                    'equipment' => 'barbell',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $result = $importer->importFromPath($path);

        $this->assertGreaterThan(0, $result['pruned']);
        $this->assertSoftDeleted($orphan);
        $this->assertNotSoftDeleted($custom);
        $this->assertDatabaseHas('exercises', [
            'slug' => 'keep-press',
            'user_id' => null,
            'deleted_at' => null,
        ]);
        $this->assertSame(1, Exercise::shared()->count());
    }

    #[Test]
    public function it_skips_pruning_when_disabled(): void
    {
        $importer = new ExerciseCatalogImporter;
        $importer->importFromPath(ExerciseCatalogImporter::defaultPath());
        $before = Exercise::shared()->count();

        $path = storage_path('framework/testing/catalog-no-prune.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'muscle_groups' => [
                ['name' => 'Chest', 'slug' => 'chest'],
            ],
            'exercises' => [
                [
                    'name' => 'Tiny Press',
                    'slug' => 'tiny-press',
                    'primary' => 'chest',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $result = $importer->importFromPath($path, prune: false);

        $this->assertSame(0, $result['pruned']);
        $this->assertSame($before + 1, Exercise::shared()->count());
    }

    #[Test]
    public function it_imports_equipment_from_a_custom_catalog(): void
    {
        $path = storage_path('framework/testing/catalog-equipment.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'muscle_groups' => [
                ['name' => 'Chest', 'slug' => 'chest'],
            ],
            'exercises' => [
                [
                    'name' => 'DB Press',
                    'slug' => 'db-press',
                    'primary' => 'chest',
                    'secondary' => null,
                    'equipment' => 'dumbbell',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $result = (new ExerciseCatalogImporter)->importFromPath($path);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseHas('exercises', [
            'slug' => 'db-press',
            'equipment' => 'dumbbell',
        ]);
    }
}
