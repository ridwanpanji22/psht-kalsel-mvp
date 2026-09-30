<?php

namespace Tests\Feature;

use App\Filament\Resources\Memberships\Pages\ListMemberships;
use App\Filament\Resources\OrganizationUnits\Pages\ListOrganizationUnits;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_can_open_authorized_resource_pages(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);
        $this->actingAs($operator);

        Livewire::test(ListOrganizationUnits::class)->assertOk();
        Livewire::test(ListMemberships::class)->assertOk();
    }

    public function test_user_without_unit_assignment_cannot_enter_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertForbidden();
    }
}
