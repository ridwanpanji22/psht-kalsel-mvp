<?php

namespace Tests\Feature;

use App\Enums\MembershipType;
use App\Enums\PublicationStatus;
use App\Filament\Resources\Memberships\Pages\CreateMembership;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\OrganizationUnit;
use App\Models\Page;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentMembershipWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_creates_student_in_assigned_unit_through_panel(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);
        $this->actingAs($operator);

        Livewire::test(CreateMembership::class)
            ->fillForm([
                'person_name' => 'Siswa Panel',
                'organization_unit_id' => $unit->id,
                'type' => MembershipType::Siswa->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('people', ['name' => 'Siswa Panel']);
        $this->assertDatabaseHas('memberships', [
            'organization_unit_id' => $unit->id,
            'type' => MembershipType::Siswa->value,
        ]);
    }

    public function test_operator_submits_content_for_independent_review_from_panel(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);
        $page = Page::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => PublicationStatus::Draft,
        ]);
        $this->actingAs($operator);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction('submitForReview')
            ->assertHasNoActionErrors();

        $this->assertSame(PublicationStatus::PendingReview, $page->refresh()->status);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'content.submitted',
            'subject_id' => $page->id,
        ]);
    }
}
