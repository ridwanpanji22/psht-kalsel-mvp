<?php

namespace Tests\Feature;

use App\Actions\ApproveWargaConversion;
use App\Actions\ChangeMembershipStatus;
use App\Actions\CreateMembership;
use App\Actions\RequestWargaConversion;
use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\MembershipStatusHistory;
use App\Models\OrganizationUnit;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MembershipLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_student_without_account_or_member_number(): void
    {
        $operator = User::factory()->create();
        $unit = OrganizationUnit::factory()->create();

        $membership = app(CreateMembership::class)->handle($operator, [
            'name' => 'Siswa Baru',
            'organization_unit_id' => $unit->id,
            'type' => MembershipType::Siswa,
        ]);

        $this->assertSame(MembershipType::Siswa, $membership->type);
        $this->assertNull($membership->member_number);
        $this->assertNull($membership->person->user_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_records_existing_warga_with_externally_issued_unique_number(): void
    {
        $operator = User::factory()->create();
        $unit = OrganizationUnit::factory()->create();

        $membership = app(CreateMembership::class)->handle($operator, [
            'name' => 'Warga Lama',
            'organization_unit_id' => $unit->id,
            'type' => MembershipType::Warga,
            'member_number' => 'PSHT-001',
        ]);

        $this->assertSame('PSHT-001', $membership->member_number);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'membership.created',
            'subject_id' => $membership->id,
        ]);
    }

    public function test_rejects_warga_without_external_number(): void
    {
        $this->expectException(ValidationException::class);

        app(CreateMembership::class)->handle(User::factory()->create(), [
            'name' => 'Warga Tanpa Nomor',
            'organization_unit_id' => OrganizationUnit::factory()->create()->id,
            'type' => MembershipType::Warga,
            'member_number' => null,
        ]);
    }

    public function test_rejects_duplicate_warga_number_without_creating_person(): void
    {
        $operator = User::factory()->create();
        $unit = OrganizationUnit::factory()->create();
        app(CreateMembership::class)->handle($operator, [
            'name' => 'Warga Pertama',
            'organization_unit_id' => $unit->id,
            'type' => MembershipType::Warga,
            'member_number' => 'PSHT-001',
        ]);

        try {
            app(CreateMembership::class)->handle($operator, [
                'name' => 'Warga Kedua',
                'organization_unit_id' => $unit->id,
                'type' => MembershipType::Warga,
                'member_number' => 'PSHT-001',
            ]);
            $this->fail('Duplicate member number was accepted.');
        } catch (ValidationException) {
            $this->assertSame(1, Person::query()->count());
        }
    }

    public function test_approving_student_conversion_keeps_one_person_and_records_history(): void
    {
        $operator = User::factory()->create();
        $reviewer = User::factory()->create();
        $membership = app(CreateMembership::class)->handle($operator, [
            'name' => 'Siswa Disahkan',
            'organization_unit_id' => OrganizationUnit::factory()->create()->id,
            'type' => MembershipType::Siswa,
        ]);

        $request = app(RequestWargaConversion::class)->handle($operator, $membership, 'PSHT-100');
        $approved = app(ApproveWargaConversion::class)->handle($reviewer, $request);

        $this->assertSame(MembershipType::Warga, $approved->type);
        $this->assertSame('PSHT-100', $approved->member_number);
        $this->assertSame($membership->person_id, $approved->person_id);
        $this->assertSame(1, Person::query()->count());
        $this->assertDatabaseHas('membership_status_histories', [
            'membership_id' => $membership->id,
            'to_status' => MembershipStatus::Active->value,
        ]);
    }

    public function test_archives_membership_without_deleting_history_or_person(): void
    {
        $operator = User::factory()->create();
        $membership = app(CreateMembership::class)->handle($operator, [
            'name' => 'Siswa Diarsipkan',
            'organization_unit_id' => OrganizationUnit::factory()->create()->id,
            'type' => MembershipType::Siswa,
        ]);

        app(ChangeMembershipStatus::class)->handle($operator, $membership, MembershipStatus::Archived);

        $this->assertSame(MembershipStatus::Archived, $membership->refresh()->status);
        $this->assertDatabaseHas('membership_status_histories', [
            'membership_id' => $membership->id,
            'to_status' => MembershipStatus::Archived->value,
        ]);
        $this->assertSame(1, Person::query()->count());
        $this->assertSame(1, Membership::query()->count());
        $this->assertGreaterThan(0, MembershipStatusHistory::query()->count());
        $this->assertGreaterThan(0, AuditEvent::query()->count());
        $this->assertSame(0, ApprovalRequest::query()->count());
    }
}
