<?php

namespace Tests\Feature;

use App\Filament\Resources\MembershipStatusHistories\MembershipStatusHistoryResource;
use App\Filament\Resources\UnitAssignments\UnitAssignmentResource;
use App\Models\Membership;
use App\Models\MembershipStatusHistory;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ImmutableHistoryPanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_status_history_resource_has_no_create_or_edit_page(): void
    {
        $this->assertArrayNotHasKey('create', MembershipStatusHistoryResource::getPages());
        $this->assertArrayNotHasKey('edit', MembershipStatusHistoryResource::getPages());
    }

    public function test_status_history_query_hides_records_outside_operator_scope(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $otherUnit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);
        $visibleMembership = Membership::factory()->create(['organization_unit_id' => $unit->id]);
        $hiddenMembership = Membership::factory()->create(['organization_unit_id' => $otherUnit->id]);
        MembershipStatusHistory::query()->create([
            'membership_id' => $visibleMembership->id,
            'to_status' => 'active',
        ]);
        MembershipStatusHistory::query()->create([
            'membership_id' => $hiddenMembership->id,
            'to_status' => 'active',
        ]);
        $this->actingAs($operator);

        $history = MembershipStatusHistoryResource::getEloquentQuery()->get();

        $this->assertCount(1, $history);
        $this->assertSame($visibleMembership->id, $history->first()->membership_id);
    }

    public function test_assignment_resource_has_no_edit_page(): void
    {
        $this->assertArrayNotHasKey('edit', UnitAssignmentResource::getPages());
    }
}
