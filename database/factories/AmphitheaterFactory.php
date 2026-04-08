<?php

namespace Database\Factories;

use App\Models\Amphitheater;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Amphitheater>
 */
class AmphitheaterFactory extends Factory
{
    protected $model = Amphitheater::class;

    private static int $sortOrder = 0;

    public function definition(): array
    {
        return [
            'name'       => fake()->unique()->words(2, true),
            'capacity'   => fake()->numberBetween(50, 300),
            'sort_order' => ++self::$sortOrder,
            'seat_layout' => null,
        ];
    }

    public function withSeats(array $seats): static
    {
        return $this->state(['seat_layout' => $seats]);
    }

    public function named(string $name, int $capacity = 100): static
    {
        return $this->state(['name' => $name, 'capacity' => $capacity]);
    }
}
