<?php

namespace Database\Factories;

use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitAssignment>
 */
class UnitAssignmentFactory extends Factory
{
    protected $model = UnitAssignment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_unit_id' => OrganizationUnit::factory(),
            'role' => 'operator',
            'is_active' => true,
        ];
    }
}
