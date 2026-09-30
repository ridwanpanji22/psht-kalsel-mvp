<?php

namespace Tests\Feature;

use App\Filament\Resources\Memberships\MembershipResource;
use App\Models\Membership;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FilamentUnitIsolationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_membership_resource_query_only_returns_assigned_unit_subtree(): void
    {
        $branch = OrganizationUnit::factory()->create();
        $child = OrganizationUnit::factory()->create(['parent_id' => $branch->id]);
        $outside = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $branch->id,
        ]);
        $visible = Membership::factory()->create(['organization_unit_id' => $child->id]);
        Membership::factory()->create(['organization_unit_id' => $outside->id]);
        $this->actingAs($operator);

        $memberships = MembershipResource::getEloquentQuery()->get();

        $this->assertCount(1, $memberships);
        $this->assertTrue($memberships->first()->is($visible));
        $this->assertInstanceOf(Builder::class, MembershipResource::getEloquentQuery());
    }
}
