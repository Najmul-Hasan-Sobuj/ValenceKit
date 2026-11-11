<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => fake()->unique()->bothify('EMP-########'),
            'name' => fake()->name(),
            'department_id' => fn () => Department::create(['name' => fake()->unique()->company()])->id,
            'designation' => 'Developer',
            'joined_on' => '2026-01-01',
            'is_active' => true,
        ];
    }
}
