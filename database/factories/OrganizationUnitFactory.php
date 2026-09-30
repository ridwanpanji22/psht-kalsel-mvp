<?php

namespace Database\Factories;

use App\Models\OrganizationUnit;
use App\OrganizationUnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationUnit>
 */
class OrganizationUnitFactory extends Factory
{
    protected $model = OrganizationUnit::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => OrganizationUnitType::Provinsi,
            'parent_id' => null,
            'supervising_branch_id' => null,
            'is_active' => true,
        ];
    }
}
