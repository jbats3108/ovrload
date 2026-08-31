<?php

namespace Database\Seeders;

use App\ExerciseProfiles\Services\ExerciseProfileBackfillService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ExerciseSeeder::class,
            ExerciseProfileSeeder::class,
            LocalExerciseProfileSeeder::class,
            RoutineSeeder::class,
        ]);

        app(ExerciseProfileBackfillService::class)->run();

        $this->call(LocalBlankSlateUserSeeder::class);
    }
}
