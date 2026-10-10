<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use App\Filament\Resources\MembershipStatusHistories\Pages\ListMembershipStatusHistories;
use App\Models\Membership;
use App\Models\MembershipStatusHistory;
use App\Models\OrganizationUnit;
use App\Models\Person;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for rendering membership status-history rows.
 *
 * `from_status`/`to_status` are cast to the {@see MembershipStatus} enum by the
 * model, so a table cell receives an enum instance rather than a scalar. The
 * list must render such a row without throwing "Object of class
 * App\Enums\MembershipStatus could not be converted to string", which only
 * surfaces once real history rows exist for the operator's scope.
 */
class FilamentMembershipStatusHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function operatorFor(OrganizationUnit $unit): User
    {
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);

        return $operator;
    }

    private function membershipIn(OrganizationUnit $unit): Membership
    {
        $person = Person::factory()->create();

        return Membership::factory()->create([
            'person_id' => $person->id,
            'organization_unit_id' => $unit->id,
            'type' => MembershipType::Siswa,
            'status' => MembershipStatus::Active,
        ]);
    }

    public function test_list_renders_rows_whose_statuses_are_cast_to_the_enum(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $operator = $this->operatorFor($unit);
        $membership = $this->membershipIn($unit);

        // A transition row with non-null from/to statuses exercises the enum
        // formatting path that previously threw on the scalar cast.
        MembershipStatusHistory::query()->create([
            'membership_id' => $membership->id,
            'actor_id' => $operator->id,
            'from_status' => MembershipStatus::Inactive,
            'to_status' => MembershipStatus::Active,
            'reason' => 'demo_status_reactivated',
        ]);

        // A creation row carries a null from_status and must render the dash.
        MembershipStatusHistory::query()->create([
            'membership_id' => $membership->id,
            'actor_id' => $operator->id,
            'from_status' => null,
            'to_status' => MembershipStatus::Active,
            'reason' => 'created',
        ]);

        $this->actingAs($operator);

        Livewire::test(ListMembershipStatusHistories::class)
            ->assertOk()
            ->assertCanSeeTableRecords(MembershipStatusHistory::query()->get())
            ->assertSee(MembershipStatus::Inactive->label())
            ->assertSee(MembershipStatus::Active->label());
    }
}
