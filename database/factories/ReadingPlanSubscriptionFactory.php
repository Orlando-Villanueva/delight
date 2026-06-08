<?php

namespace Database\Factories;

use App\Models\ReadingPlan;
use App\Models\ReadingPlanSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReadingPlanSubscriptionFactory extends Factory
{
    protected $model = ReadingPlanSubscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reading_plan_id' => ReadingPlan::factory(),
            'started_at' => now(),
            'start_day' => 1,
            'is_active' => true,
        ];
    }
}
