<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\MembershipStatusHistory;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\OrganizationUnitType;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\MembershipStatusHistoryPolicy;
use Database\Seeders\DemoDatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_demo_seed_creates_required_organization_shape(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $province = OrganizationUnit::query()
            ->where('type', OrganizationUnitType::Provinsi)
            ->get();
        $this->assertCount(1, $province, 'There must be exactly one province root.');

        $branches = OrganizationUnit::query()
            ->where('type', OrganizationUnitType::Cabang)
            ->get();
        $this->assertCount(13, $branches, 'There must be exactly 13 branches.');

        foreach ($branches as $branch) {
            $this->assertSame($province->first()->id, $branch->parent_id);

            $specialUnits = OrganizationUnit::query()
                ->where('parent_id', $branch->id)
                ->whereIn('type', [OrganizationUnitType::Ukm, OrganizationUnitType::Komisariat])
                ->get();

            $this->assertSame(
                1,
                $specialUnits->where('type', OrganizationUnitType::Ukm)->count(),
                "Branch {$branch->name} must have exactly one UKM.",
            );
            $this->assertSame(
                1,
                $specialUnits->where('type', OrganizationUnitType::Komisariat)->count(),
                "Branch {$branch->name} must have exactly one Komisariat.",
            );

            $rantings = OrganizationUnit::query()
                ->where('parent_id', $branch->id)
                ->where('type', OrganizationUnitType::Ranting)
                ->get();

            $this->assertGreaterThanOrEqual(
                5,
                $rantings->count(),
                "Branch {$branch->name} must have at least five rantings.",
            );

            foreach ($rantings as $ranting) {
                $rayonCount = OrganizationUnit::query()
                    ->where('parent_id', $ranting->id)
                    ->where('type', OrganizationUnitType::Rayon)
                    ->count();

                $this->assertGreaterThanOrEqual(2, $rayonCount, "Ranting {$ranting->name} needs at least 2 rayons.");
                $this->assertLessThanOrEqual(5, $rayonCount, "Ranting {$ranting->name} may have at most 5 rayons.");
            }
        }
    }

    public function test_demo_seed_creates_one_active_onboarded_operator_per_unit(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        // 1 province + 13 branches + 65 rantings + 208 rayons + 13 UKM + 13 Komisariat = 313.
        $expectedOperators = 1
            + OrganizationUnit::query()->where('type', OrganizationUnitType::Cabang)->count()
            + OrganizationUnit::query()->where('type', OrganizationUnitType::Ranting)->count()
            + OrganizationUnit::query()->where('type', OrganizationUnitType::Rayon)->count()
            + OrganizationUnit::query()->where('type', OrganizationUnitType::Ukm)->count()
            + OrganizationUnit::query()->where('type', OrganizationUnitType::Komisariat)->count();

        $this->assertSame(313, $expectedOperators, 'Presentation scope must total 313 units.');

        $operators = User::query()->get();

        $this->assertCount(313, $operators, 'There must be exactly one demo operator per generated unit.');

        $uids = [];
        foreach ($operators as $operator) {
            $this->assertTrue($operator->is_active, "Operator {$operator->email} must be active.");
            $this->assertNotNull($operator->onboarded_at, "Operator {$operator->email} must be onboarded.");
            $this->assertTrue($operator->isOnboarded(), "Operator {$operator->email} must report onboarded.");
            $this->assertStringEndsWith('.test', (string) $operator->email, 'Demo emails must use the .test domain.');
            $this->assertNotNull($operator->firebase_uid, 'Every operator needs a dummy Firebase UID.');
            $this->assertStringStartsWith('demo-', (string) $operator->firebase_uid, 'Dummy UIDs must use the demo- prefix.');

            $this->assertTrue(
                UnitAssignment::query()
                    ->where('user_id', $operator->id)
                    ->where('is_active', true)
                    ->exists(),
                "Operator {$operator->email} must have at least one active assignment.",
            );

            $uids[] = $operator->firebase_uid;
        }

        $this->assertSame(
            count($uids),
            count(array_unique($uids)),
            'Every dummy Firebase UID must be unique.',
        );
    }

    public function test_demo_seed_covers_all_six_unit_types_with_operators(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        foreach (OrganizationUnitType::cases() as $type) {
            $unitIds = OrganizationUnit::query()->where('type', $type)->pluck('id');

            $this->assertTrue(
                UnitAssignment::query()
                    ->whereIn('organization_unit_id', $unitIds)
                    ->where('is_active', true)
                    ->exists(),
                "No active operator assignment found for unit type {$type->value}.",
            );
        }
    }

    public function test_demo_seed_uses_the_shared_demo_password(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $operator = User::query()->firstOrFail();

        $this->assertTrue(
            Hash::check('PshtKalsel@1922', $operator->password),
            'Seeded operators must authenticate with the shared demo password.',
        );
    }

    public function test_demo_seed_creates_public_and_private_organization_positions(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        // Province plus every branch gets a current public position.
        $this->assertSame(
            1 + 13,
            OrganizationPosition::query()->where('is_public', true)->whereNull('ends_on')->count(),
            'Province and each branch must expose a current public position.',
        );

        // Historical (ended) positions exercise the history contract.
        $ended = OrganizationPosition::query()->whereNotNull('ends_on')->count();
        $this->assertGreaterThanOrEqual(5, $ended, 'At least five branches need a historical position.');

        // A subset is deliberately non-public to exercise public filtering.
        $this->assertGreaterThan(
            0,
            OrganizationPosition::query()->where('is_public', false)->count(),
            'Some positions must be non-public for filtering.',
        );
    }

    public function test_demo_seed_operator_data_is_rerunnable(): void
    {
        $this->seed(DemoDatabaseSeeder::class);
        $operators = User::query()->count();
        $assignments = UnitAssignment::query()->count();
        $positions = OrganizationPosition::query()->count();

        $this->seed(DemoDatabaseSeeder::class);

        $this->assertSame($operators, User::query()->count(), 'Rerun must not duplicate operators.');
        $this->assertSame($assignments, UnitAssignment::query()->count(), 'Rerun must not duplicate assignments.');
        $this->assertSame($positions, OrganizationPosition::query()->count(), 'Rerun must not duplicate positions.');
    }

    public function test_demo_seed_is_rerunnable_without_duplicate_natural_keys(): void
    {
        $this->seed(DemoDatabaseSeeder::class);
        $firstCounts = $this->organizationCounts();

        $this->seed(DemoDatabaseSeeder::class);

        $this->assertSame($firstCounts, $this->organizationCounts());
    }

    public function test_demo_seed_is_rejected_in_production(): void
    {
        app()->detectEnvironment(fn (): string => 'production');

        $this->withoutMockingConsoleOutput();

        $this->expectException(\RuntimeException::class);

        (new DemoDatabaseSeeder)->run();
    }

    public function test_demo_seed_creates_membership_distribution_invariants(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertSame(1800, Membership::query()->count(), 'The demo dataset must contain exactly 1,800 memberships.');

        foreach (MembershipType::cases() as $type) {
            $this->assertGreaterThan(0, Membership::query()->where('type', $type)->count(), "Membership type {$type->value} must be represented.");
        }

        foreach (OrganizationUnitType::cases() as $unitType) {
            $unitIds = OrganizationUnit::query()->where('type', $unitType)->pluck('id');

            $this->assertGreaterThan(
                0,
                Membership::query()->whereIn('organization_unit_id', $unitIds)->count(),
                "Memberships must cover unit type {$unitType->value}.",
            );
        }

        foreach (MembershipStatus::cases() as $status) {
            $this->assertGreaterThan(0, Membership::query()->where('status', $status)->count(), "Membership status {$status->value} must be represented.");
        }

        // Member numbers: unique and non-null for warga, always null for siswa.
        $warga = Membership::query()->where('type', MembershipType::Warga)->get();
        $this->assertGreaterThan(0, $warga->count(), 'There must be warga memberships.');
        $this->assertNotNull($warga->whereNull('member_number')->first() === null, 'Every warga must have a member number.');

        $wargaNumbers = $warga->pluck('member_number')->all();
        $this->assertSame(count($wargaNumbers), count(array_unique($wargaNumbers)), 'Warga member numbers must be unique.');

        $this->assertSame(
            0,
            Membership::query()->where('type', MembershipType::Siswa)->whereNotNull('member_number')->count(),
            'Siswa memberships must not carry a member number.',
        );
    }

    public function test_demo_seed_creates_status_histories_and_valid_transitions(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertGreaterThanOrEqual(20, MembershipStatusHistory::query()->count(), 'At least 20 status histories are required.');

        foreach (MembershipStatusHistory::query()->get() as $history) {
            $this->assertNotNull($history->actor_id, 'Every status history must carry an actor.');
            $this->assertTrue(
                $history->to_status instanceof MembershipStatus,
                'Status histories must cast to MembershipStatus.',
            );

            // The warga conversion record is a *type* change, not a status
            // transition: ApproveWargaConversion writes from_status == to_status
            // on purpose. Every other row must be an enum-valid transition.
            if ($history->reason === 'warga_conversion_approved') {
                $this->assertSame($history->from_status, $history->to_status);

                continue;
            }

            if ($history->from_status !== null) {
                $this->assertTrue(
                    $history->from_status->canTransitionTo($history->to_status),
                    "Transition {$history->from_status->value} -> {$history->to_status->value} must be enum-valid.",
                );
            }
        }

        // Archived memberships must carry archived_at, others must not.
        $this->assertSame(
            Membership::query()->where('status', MembershipStatus::Archived)->count(),
            Membership::query()->whereNotNull('archived_at')->count(),
            'archived_at must be set exactly for archived memberships.',
        );

        // History rows must be reviewable by the unit's own operator.
        $history = MembershipStatusHistory::query()->firstOrFail();
        $operator = $this->operatorAssignedTo($history->membership->organization_unit_id);

        $this->assertNotNull($operator, 'Each membership unit must have an operator.');
        $this->assertTrue(
            app(MembershipStatusHistoryPolicy::class)->view($operator, $history),
            'The unit operator must be able to review the membership history it created.',
        );
    }

    public function test_demo_seed_creates_warga_conversion_requests_with_all_statuses(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $requests = ApprovalRequest::query()->where('type', 'warga_conversion')->get();

        $this->assertSame(30, $requests->count(), 'There must be exactly 30 warga conversion requests.');
        $this->assertSame(10, $requests->where('status', ApprovalStatus::Pending)->count());
        $this->assertSame(10, $requests->where('status', ApprovalStatus::Approved)->count());
        $this->assertSame(10, $requests->where('status', ApprovalStatus::Rejected)->count());

        // Every approval touches a siswa membership and carries a unique member number payload.
        $payloadNumbers = [];
        foreach ($requests as $request) {
            $this->assertNotNull($request->payload['member_number'] ?? null, 'Approval payload must carry a member number.');
            $payloadNumbers[] = $request->payload['member_number'];
        }
        $this->assertSame(count($payloadNumbers), count(array_unique($payloadNumbers)), 'Approval payload member numbers must be unique.');

        // Approved conversions actually convert the membership and set its member number.
        foreach ($requests->where('status', ApprovalStatus::Approved) as $request) {
            $membership = $request->membership;
            $this->assertSame(MembershipType::Warga, $membership->type, 'Approved conversions must become warga.');
            $this->assertSame($request->payload['member_number'], $membership->member_number);
            $this->assertNotNull($request->reviewed_by_id);
            $this->assertNotNull($request->reviewed_at);
            $this->assertNotSame($request->requested_by_id, $request->reviewed_by_id);
        }

        // Rejected requests are reviewed but leave the membership untouched.
        foreach ($requests->where('status', ApprovalStatus::Rejected) as $request) {
            $this->assertNotNull($request->reviewed_by_id);
            $this->assertNotNull($request->reviewed_at);
            $this->assertNotNull($request->review_note);
            $this->assertNotSame($request->requested_by_id, $request->reviewed_by_id);
            $this->assertSame(MembershipType::Siswa, $request->membership->type, 'Rejected conversions must stay siswa.');
            $this->assertNull($request->membership->member_number, 'Rejected conversions must not gain a member number.');
        }

        // Pending requests are untouched and reviewable by an in-scope operator
        // other than the requester; the requester must never review their own.
        foreach ($requests->where('status', ApprovalStatus::Pending) as $request) {
            $this->assertNull($request->reviewed_by_id);
            $this->assertNull($request->reviewed_at);

            $this->assertFalse(
                app(ApprovalRequestPolicy::class)->review($request->requester, $request),
                'A requester must never be able to review their own request.',
            );

            $reviewer = User::query()
                ->where('id', '!=', $request->requested_by_id)
                ->whereHas('unitAssignments', function ($query) use ($request): void {
                    $query->whereIn(
                        'organization_unit_id',
                        $this->ancestorUnitIds($request->membership->organization_unit_id),
                    )->where('is_active', true);
                })
                ->first();

            $this->assertNotNull($reviewer, 'A pending request must have an in-scope reviewer candidate.');

            $this->assertTrue(
                app(ApprovalRequestPolicy::class)->review($reviewer, $request),
                'An in-scope operator distinct from the requester must be able to review a pending request.',
            );
        }
    }

    /**
     * Unit ids on the path from the membership unit up to the root, i.e. the
     * operators whose scope covers the membership.
     *
     * @return list<int>
     */
    private function ancestorUnitIds(int $unitId): array
    {
        $ids = [];
        $current = OrganizationUnit::query()->find($unitId);

        while ($current !== null) {
            $ids[] = $current->id;
            $current = $current->parent;
        }

        return $ids;
    }

    public function test_demo_seed_creates_audit_events_for_seed_operations(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        foreach ([
            'membership.created',
            'membership.status_changed',
            'membership.converted_to_warga',
        ] as $action) {
            $this->assertGreaterThan(
                0,
                AuditEvent::query()->where('action', $action)->count(),
                "Audit action {$action} must be present.",
            );
        }

        foreach (AuditEvent::query()->get() as $event) {
            $this->assertNotSame('', (string) $event->subject_type, 'Audit events must reference a subject.');
            $this->assertNotNull($event->subject_id);
        }
    }

    public function test_demo_seed_membership_data_is_rerunnable(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $counts = [
            'people' => Membership::query()->count(),
            'memberships' => Membership::query()->count(),
            'histories' => MembershipStatusHistory::query()->count(),
            'approvals' => ApprovalRequest::query()->count(),
            'audits' => AuditEvent::query()->count(),
        ];

        $this->seed(DemoDatabaseSeeder::class);

        $this->assertSame($counts['memberships'], Membership::query()->count(), 'Rerun must not duplicate memberships.');
        $this->assertSame($counts['histories'], MembershipStatusHistory::query()->count(), 'Rerun must not duplicate status histories.');
        $this->assertSame($counts['approvals'], ApprovalRequest::query()->count(), 'Rerun must not duplicate approval requests.');
        $this->assertSame($counts['audits'], AuditEvent::query()->count(), 'Rerun must not duplicate audit events.');

        $this->assertGreaterThan(0, Membership::query()->count());
    }

    public function test_demo_seed_memberships_are_visible_to_their_unit_operator(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        // A representative sample across every unit type must be reviewable in scope.
        foreach (OrganizationUnitType::cases() as $unitType) {
            $membership = Membership::query()
                ->whereIn('organization_unit_id', OrganizationUnit::query()->where('type', $unitType)->pluck('id'))
                ->first();

            $this->assertNotNull($membership, "No membership found for unit type {$unitType->value}.");

            $operator = $this->operatorAssignedTo($membership->organization_unit_id);
            $this->assertNotNull($operator, "No operator for unit type {$unitType->value}.");

            $this->assertTrue(
                app(MembershipPolicy::class)->view($operator, $membership),
                "Membership in {$unitType->value} must be visible to its unit operator.",
            );
        }
    }

    /**
     * Resolve the seeded operator assigned directly to a unit.
     */
    private function operatorAssignedTo(int $unitId): ?User
    {
        return User::query()
            ->whereHas('unitAssignments', function ($query) use ($unitId): void {
                $query->where('organization_unit_id', $unitId)->where('is_active', true);
            })
            ->first();
    }

    /**
     * @return array<string, int>
     */
    private function organizationCounts(): array
    {
        $counts = [];

        foreach (OrganizationUnitType::cases() as $type) {
            $counts[$type->value] = OrganizationUnit::query()->where('type', $type)->count();
        }

        return $counts;
    }
}
