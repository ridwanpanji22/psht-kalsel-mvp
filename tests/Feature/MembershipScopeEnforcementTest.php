<?php

namespace Tests\Feature;

use App\Actions\CreateMembership;
use App\Enums\MembershipType;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MembershipScopeEnforcementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_cannot_create_membership_for_unit_outside_scope(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $otherUnit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateMembership::class)->handle($operator, [
            'name' => 'Di luar cakupan',
            'organization_unit_id' => $otherUnit->id,
            'type' => MembershipType::Siswa,
        ]);
    }

    public function test_operator_can_create_membership_for_child_unit_in_scope(): void
    {
        $branch = OrganizationUnit::factory()->create();
        $child = OrganizationUnit::factory()->create(['parent_id' => $branch->id]);
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $branch->id,
        ]);

        $membership = app(CreateMembership::class)->handle($operator, [
            'name' => 'Di dalam cakupan',
            'organization_unit_id' => $child->id,
            'type' => MembershipType::Siswa,
        ]);

        $this->assertSame($child->id, $membership->organization_unit_id);
    }
}
