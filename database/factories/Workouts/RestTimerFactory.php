<?php

namespace Database\Factories\Workouts;

use App\Workouts\Models\RestTimer;
use App\Workouts\Models\Workout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestTimer>
 */
class RestTimerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workout_id' => Workout::factory(),
            'ends_at' => now()->addMinutes(2),
        ];
    }
}
