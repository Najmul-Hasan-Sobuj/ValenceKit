<?php

namespace Database\Factories;

use App\Models\Proposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proposal>
 */
class ProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'PROP-'.fake()->unique()->numerify('#####'),
            'type' => 'proposal',
            'status' => 'draft',
            'title' => fake()->sentence(4),
            'client_name' => fake()->company(),
            'client_email' => fake()->companyEmail(),
            'currency' => 'BDT',
            'items' => [['description' => 'Professional services', 'quantity' => 1, 'unit_price_minor' => 100000]],
            'subtotal_minor' => 100000,
            'discount_minor' => 0,
            'tax_rate_basis_points' => 0,
            'tax_minor' => 0,
            'total_minor' => 100000,
            'issued_on' => now()->toDateString(),
            'valid_until' => now()->addDays(14)->toDateString(),
        ];
    }
}
