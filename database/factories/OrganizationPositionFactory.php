<?php

namespace Database\Factories;

use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationPosition>
 */
class OrganizationPositionFactory extends Factory
{
    protected $model = OrganizationPosition::class;

    public function definition(): array
    {
        return [
            'organization_unit_id' => OrganizationUnit::factory(),
            'title' => 'Ketua',
            'holder_name' => fake()->name(),
            'starts_on' => '2026-01-01',
            'ends_on' => null,
            'is_public' => false,
        ];
    }
}
