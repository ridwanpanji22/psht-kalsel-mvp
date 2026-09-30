<?php

namespace Tests\Feature;

use App\Enums\MembershipType;
use App\Models\Membership;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\Policies\MembershipPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MembershipAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_can_view_membership_in_assigned_unit_subtree(): void
    {
        $branch = OrganizationUnit::factory()->create();
        $ranting = OrganizationUnit::factory()->create(['parent_id' => $branch->id]);
        $user = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $user->id,
            'organization_unit_id' => $branch->id,
        ]);
        $membership = Membership::factory()->create([
            'organization_unit_id' => $ranting->id,
            'type' => MembershipType::Siswa,
        ]);

        $this->assertTrue(app(MembershipPolicy::class)->view($user, $membership));
    }

    public function test_operator_cannot_view_membership_outside_assigned_unit_subtree(): void
    {
        $branch = OrganizationUnit::factory()->create();
        $otherBranch = OrganizationUnit::factory()->create();
        $user = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $user->id,
            'organization_unit_id' => $branch->id,
        ]);
        $membership = Membership::factory()->create([
            'organization_unit_id' => $otherBranch->id,
            'type' => MembershipType::Siswa,
        ]);

        $this->assertFalse(app(MembershipPolicy::class)->view($user, $membership));
    }

    public function test_operator_cannot_permanently_delete_membership(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $user = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $user->id,
            'organization_unit_id' => $unit->id,
        ]);
        $membership = Membership::factory()->create(['organization_unit_id' => $unit->id]);

        $this->assertFalse(app(MembershipPolicy::class)->forceDelete($user, $membership));
    }
}
