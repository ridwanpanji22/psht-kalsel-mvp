<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use App\Models\Membership;
use App\Models\OrganizationUnit;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'organization_unit_id' => OrganizationUnit::factory(),
            'type' => MembershipType::Siswa,
            'status' => MembershipStatus::Active,
            'member_number' => null,
            'started_on' => now()->toDateString(),
            'archived_at' => null,
        ];
    }
}
