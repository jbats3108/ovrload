<?php

namespace Database\Factories;

use App\Exercises\Enums\ExerciseEquipment;
use App\Exercises\Models\Exercise;
use App\MuscleGroups\Models\MuscleGroup;
use App\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Exercise>
 */
class ExerciseFactory extends Factory
{
    protected $model = Exercise::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'slug' => $this->faker->slug(),
            'primary_muscle_group_id' => MuscleGroup::factory(),
            'secondary_muscle_group_id' => null,
            'equipment' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    public function barbell(): static
    {
        return $this->state(fn (): array => [
            'equipment' => ExerciseEquipment::Barbell,
        ]);
    }

    public function dumbbell(): static
    {
        return $this->state(fn (): array => [
            'equipment' => ExerciseEquipment::Dumbbell,
        ]);
    }

    public function custom(?User $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user !== null ? $user->id : User::factory(),
        ]);
    }

    public function shared(): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
        ]);
    }
}
