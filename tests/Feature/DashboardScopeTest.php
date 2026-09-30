<?php

namespace Tests\Feature;

use App\Enums\MembershipType;
use App\Filament\Widgets\OrganizationOverview;
use App\Models\Membership;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardScopeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_dashboard_counts_only_memberships_in_assigned_unit_scope(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $otherUnit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);
        Membership::factory()->count(2)->create([
            'organization_unit_id' => $unit->id,
            'type' => MembershipType::Siswa,
        ]);
        Membership::factory()->create([
            'organization_unit_id' => $otherUnit->id,
            'type' => MembershipType::Siswa,
        ]);

        $this->actingAs($operator);
        $stats = app(OrganizationOverview::class)->getStats();

        $this->assertSame('1', $stats[0]->getValue());
        $this->assertSame('2', $stats[1]->getValue());
        $this->assertSame('0', $stats[2]->getValue());
    }
}
