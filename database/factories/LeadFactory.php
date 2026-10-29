<?php

namespace Database\Factories;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '09'.fake()->numerify('#########'),
            'stage' => fake()->randomElement(LeadStage::cases()),
            'priority' => fake()->randomElement(LeadPriority::cases()),
            'source' => fake()->randomElement(LeadSource::cases()),
            'value' => fake()->numberBetween(20, 900) * 1000,
            'notes' => fake()->boolean(60) ? fake()->sentence(12) : null,
            'owner_id' => User::factory(),
        ];
    }

    public function ownedBy(User $user): static
    {
        return $this->state(fn () => ['owner_id' => $user->getKey()]);
    }

    public function stage(LeadStage $stage): static
    {
        return $this->state(fn () => ['stage' => $stage]);
    }
}
