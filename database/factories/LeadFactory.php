<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '+92300'.fake()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'source' => fake()->randomElement(['website', 'facebook', 'referral', 'webhook']),
            'status' => fake()->randomElement(LeadStatus::cases()),
        ];
    }

    /**
     * Indicate that the lead has the given status.
     */
    public function status(LeadStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
