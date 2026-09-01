<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class E2eSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ExerciseProfileSeeder::class,
            LocalExerciseProfileSeeder::class,
            E2eExerciseSeeder::class,
            RoutineSeeder::class,
        ]);

        $this->call(LocalBlankSlateUserSeeder::class);
    }
}
