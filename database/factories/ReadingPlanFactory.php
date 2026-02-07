<?php

namespace Database\Factories;

use App\Models\ReadingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReadingPlanFactory extends Factory
{
    protected $model = ReadingPlan::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->sentence(3)),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph,
            'days' => [],
            'is_active' => true,
        ];
    }
}
