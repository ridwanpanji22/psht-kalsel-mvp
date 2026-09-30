<?php

namespace Tests\Feature;

use App\Actions\CreateOrganizationPosition;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrganizationPositionHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_can_open_organization_positions_index(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $operator = User::factory()->onboarded()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);
        OrganizationPosition::factory()->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($operator)
            ->get('/admin/organization-positions')
            ->assertOk();
    }

    public function test_ending_position_preserves_previous_assignment(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $position = app(CreateOrganizationPosition::class)->handle([
            'organization_unit_id' => $unit->id,
            'title' => 'Ketua',
            'holder_name' => 'Pengurus Lama',
            'starts_on' => '2026-01-01',
            'is_public' => true,
        ]);

        $ended = app(CreateOrganizationPosition::class)->end($position, '2026-09-30');

        $this->assertSame('Pengurus Lama', $ended->holder_name);
        $this->assertSame('2026-09-30', $ended->ends_on?->toDateString());
        $this->assertSame(1, OrganizationPosition::query()->count());
    }
}
