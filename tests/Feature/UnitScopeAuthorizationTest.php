<?php

namespace Tests\Feature;

use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\OrganizationUnitType;
use App\Support\OrganizationScope;
use Filament\Panel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class UnitScopeAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_active_assigned_operators_can_access_admin_panel(): void
    {
        $activeOperator = User::factory()->onboarded()->create();
        $unit = OrganizationUnit::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $activeOperator->id,
            'organization_unit_id' => $unit->id,
        ]);
        $inactiveOperator = User::factory()->onboarded()->inactive()->create();

        $this->assertTrue($activeOperator->canAccessPanel(Panel::make()));
        $this->assertFalse($inactiveOperator->canAccessPanel(Panel::make()));
    }

    public function test_active_assigned_operator_without_onboarding_cannot_access_admin_panel(): void
    {
        $operator = User::factory()->create(['onboarded_at' => null]);
        $unit = OrganizationUnit::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->assertFalse($operator->canAccessPanel(Panel::make()));
    }

    public function test_active_assigned_onboarded_operator_can_access_admin_panel(): void
    {
        $operator = User::factory()->onboarded()->create();
        $unit = OrganizationUnit::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->assertTrue($operator->canAccessPanel(Panel::make()));
    }

    public function test_operator_scope_includes_formal_descendants_but_not_siblings_or_supervised_units(): void
    {
        $province = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $branch = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $province->id,
        ]);
        $siblingBranch = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $province->id,
        ]);
        $ranting = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Ranting,
            'parent_id' => $branch->id,
        ]);
        $rayon = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Rayon,
            'parent_id' => $ranting->id,
        ]);
        $ukm = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Ukm,
            'parent_id' => $province->id,
            'supervising_branch_id' => $branch->id,
        ]);
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $branch->id,
        ]);

        $visibleUnitIds = app(OrganizationScope::class)->visibleUnitIds($operator);

        $this->assertEqualsCanonicalizing([$branch->id, $ranting->id, $rayon->id], $visibleUnitIds);
        $this->assertNotContains($siblingBranch->id, $visibleUnitIds);
        $this->assertNotContains($ukm->id, $visibleUnitIds);
    }
}
