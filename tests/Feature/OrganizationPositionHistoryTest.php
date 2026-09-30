<?php

namespace Tests\Feature;

use App\Actions\CreateOrganizationPosition;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OrganizationPositionHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

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
