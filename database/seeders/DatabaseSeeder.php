<?php

namespace Database\Seeders;

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

        $this->call(LocalBlankSlateUserSeeder::class);
    }
}
