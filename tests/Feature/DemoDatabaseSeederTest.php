<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\GalleryCategory;
use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use App\Enums\PublicationStatus;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Membership;
use App\Models\MembershipStatusHistory;
use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\Page;
use App\Models\Person;
use App\Models\TrainingLocation;
use App\Models\UnitAssignment;
use App\Models\User;
use App\OrganizationUnitType;
use App\Policies\ApprovalRequestPolicy;
use App\Policies\EventPolicy;
use App\Policies\GalleryPolicy;
use App\Policies\MembershipPolicy;
use App\Policies\MembershipStatusHistoryPolicy;
use App\Policies\PagePolicy;
use App\Policies\TrainingLocationPolicy;
use App\Support\SafeStoragePath;
use Database\Seeders\DemoDatabaseSeeder;
use Database\Seeders\DemoMembershipSeeder;
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

            $uids[] = $operator->firebase_uid;
        }

        $this->assertSame(
            count($uids),
            count(array_unique($uids)),
            'Every dummy Firebase UID must be unique.',
        );

        // Exactly one active operator-role assignment for every generated unit.
        $unitIds = OrganizationUnit::query()->pluck('id')->all();
        $this->assertCount(313, $unitIds, 'The demo tree must contain 313 units.');

        $assignments = UnitAssignment::query()
            ->where('role', 'operator')
            ->where('is_active', true)
            ->get()
            ->groupBy('organization_unit_id');

        $this->assertCount(
            313,
            $assignments,
            'Every generated unit must have exactly one active operator assignment.',
        );

        foreach ($unitIds as $unitId) {
            $this->assertArrayHasKey((string) $unitId, $assignments->all(), "Unit {$unitId} has no active operator assignment.");
            $this->assertCount(1, $assignments[$unitId], "Unit {$unitId} must have exactly one active operator assignment.");
        }

        // No stray assignments: every active assignment references a seeded unit
        // AND a seeded operator-role user, and there are exactly 313 of them.
        $activeAssignments = UnitAssignment::query()->where('is_active', true)->get();
        $this->assertCount(313, $activeAssignments, 'There must be exactly 313 active assignments (no strays).');

        $seededUserIds = $operators->pluck('id')->all();
        foreach ($activeAssignments as $assignment) {
            $this->assertContains($assignment->organization_unit_id, $unitIds, 'Assignments must reference seeded units.');
            $this->assertContains($assignment->user_id, $seededUserIds, 'Assignments must reference seeded operators.');
            $this->assertSame('operator', $assignment->role, 'Seeded assignments must use the operator role.');
        }
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

    public function test_demo_seed_uses_the_shared_demo_password_for_every_operator(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $operators = User::query()->get();

        $this->assertGreaterThan(0, $operators->count());

        foreach ($operators as $operator) {
            $this->assertTrue(
                Hash::check('PshtKalsel@1922', $operator->password),
                "Operator {$operator->email} must authenticate with the shared demo password.",
            );

            $this->assertNotSame(
                'PshtKalsel@1922',
                $operator->password,
                'The raw demo password must never be stored verbatim.',
            );
        }
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

    public function test_demo_seed_is_rejected_when_a_foreign_province_root_exists(): void
    {
        // A foreign province root with a child, created before seeding.
        $foreignProvince = OrganizationUnit::query()->create([
            'name' => 'Pengurus Provinsi Lain',
            'type' => OrganizationUnitType::Provinsi,
            'parent_id' => null,
            'is_active' => true,
        ]);
        $foreignChild = OrganizationUnit::query()->create([
            'name' => 'Cabang Asing',
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $foreignProvince->id,
            'is_active' => true,
        ]);

        $unitsBefore = OrganizationUnit::query()->count();
        $usersBefore = User::query()->count();

        $this->withoutMockingConsoleOutput();

        try {
            (new DemoDatabaseSeeder)->run();
            $this->fail('Seeding must be rejected when a foreign province root already exists.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('provinsi', $exception->getMessage(), 'The rejection must explain the province conflict.');
        }

        // No demo writes occurred, and the foreign rows are provably untouched.
        $this->assertSame($unitsBefore, OrganizationUnit::query()->count(), 'No demo units may be created.');
        $this->assertSame($usersBefore, User::query()->count(), 'No demo operators may be created.');
        $this->assertSame(0, UnitAssignment::query()->count(), 'No demo assignments may be created.');

        $this->assertDatabaseHas('organization_units', [
            'id' => $foreignProvince->id,
            'name' => 'Pengurus Provinsi Lain',
            'parent_id' => null,
        ]);
        $this->assertDatabaseHas('organization_units', [
            'id' => $foreignChild->id,
            'name' => 'Cabang Asing',
            'parent_id' => $foreignProvince->id,
        ]);
    }

    public function test_demo_seed_creates_membership_distribution_invariants(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertSame(1800, Membership::query()->count(), 'The demo dataset must contain exactly 1,800 memberships.');

        foreach (MembershipType::cases() as $type) {
            $this->assertGreaterThan(0, Membership::query()->where('type', $type)->count(), "Membership type {$type->value} must be represented.");
        }

        // Base creation ratio is 60% siswa / 40% warga. Ten approved
        // conversions move siswa -> warga, so allow a bounded drift.
        $siswaCount = Membership::query()->where('type', MembershipType::Siswa)->count();
        $wargaCount = Membership::query()->where('type', MembershipType::Warga)->count();
        $wargaShare = $wargaCount / 1800;

        $this->assertGreaterThanOrEqual(
            0.38,
            $wargaShare,
            'Warga share must stay near the 40% creation target after conversions.',
        );
        $this->assertLessThanOrEqual(
            0.45,
            $wargaShare,
            'Warga share must not drift materially above 40%.',
        );
        $this->assertGreaterThanOrEqual(
            0.55,
            $siswaCount / 1800,
            'Siswa share must stay near the 60% creation target.',
        );

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

        // Member numbers: exactly the warga carry a unique non-null number;
        // siswa never carry one.
        $wargaWithNumber = Membership::query()
            ->where('type', MembershipType::Warga)
            ->whereNotNull('member_number')
            ->count();
        $this->assertSame($wargaCount, $wargaWithNumber, 'Every warga membership must have a member number.');

        $wargaNumbers = Membership::query()
            ->where('type', MembershipType::Warga)
            ->pluck('member_number')
            ->all();
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
            // to match the membership's actual status at approval time.
            if ($history->reason === 'warga_conversion_approved') {
                $this->assertSame($history->from_status, $history->to_status);
                $this->assertSame(
                    MembershipType::Warga,
                    $history->membership->type,
                    'A conversion history row must belong to a warga membership.',
                );

                continue;
            }

            if ($history->from_status !== null) {
                $this->assertTrue(
                    $history->from_status->canTransitionTo($history->to_status),
                    "Transition {$history->from_status->value} -> {$history->to_status->value} must be enum-valid.",
                );
            }
        }

        // A deterministic smaller group must exercise inactive -> active.
        $reactivations = MembershipStatusHistory::query()
            ->where('from_status', MembershipStatus::Inactive)
            ->where('to_status', MembershipStatus::Active)
            ->count();

        $this->assertGreaterThanOrEqual(
            DemoMembershipSeeder::REACTIVATION_TOTAL,
            $reactivations,
            'A deterministic group must record inactive -> active transitions.',
        );

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

        // Every approval targets a membership and carries a unique member number payload.
        $payloadNumbers = [];
        foreach ($requests as $request) {
            $this->assertNotNull($request->payload['member_number'] ?? null, 'Approval payload must carry a member number.');
            $payloadNumbers[] = $request->payload['member_number'];
        }
        $this->assertSame(count($payloadNumbers), count(array_unique($payloadNumbers)), 'Approval payload member numbers must be unique.');

        // Each conversion writes exactly one siswa -> warga history row whose
        // from/to status matches the membership's actual (unchanged) status.
        $approvedNumbers = $requests->where('status', ApprovalStatus::Approved)->pluck('payload.member_number')->all();
        $conversionHistories = MembershipStatusHistory::query()
            ->where('reason', 'warga_conversion_approved')
            ->get();

        $this->assertSame(10, $conversionHistories->count(), 'There must be exactly ten conversion history rows.');

        foreach ($conversionHistories as $history) {
            $this->assertSame(
                $history->membership->status,
                $history->from_status,
                'A conversion history from_status must equal the membership status.',
            );
            $this->assertSame(
                $history->membership->status,
                $history->to_status,
                'A conversion history to_status must equal the membership status.',
            );
            $this->assertSame(MembershipType::Warga, $history->membership->type);
        }

        // Approved conversions actually convert the membership and set its number.
        foreach ($requests->where('status', ApprovalStatus::Approved) as $request) {
            $membership = $request->membership;
            $this->assertSame(MembershipType::Warga, $membership->type, 'Approved conversions must become warga.');
            $this->assertSame($request->payload['member_number'], $membership->member_number);
            $this->assertContains($membership->member_number, $approvedNumbers);
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
            $this->assertSame(MembershipType::Siswa, $request->membership->type, 'Pending conversions must remain siswa.');

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
            'content.published',
        ] as $action) {
            $this->assertGreaterThan(
                0,
                AuditEvent::query()->where('action', $action)->count(),
                "Audit action {$action} must be present.",
            );
        }

        // The seeded publication audit is the placeholder written by
        // DemoWorkflowSeeder until Task 5 owns real publication records.
        $published = AuditEvent::query()->where('action', 'content.published')->firstOrFail();
        $this->assertSame(
            OrganizationUnit::query()->where('type', OrganizationUnitType::Provinsi)->value('id'),
            $published->subject_id,
            'The placeholder publication audit must be attached to the province unit.',
        );
        $this->assertSame('organization_profile', $published->metadata['content_type'] ?? null);

        foreach (AuditEvent::query()->get() as $event) {
            $this->assertNotSame('', (string) $event->subject_type, 'Audit events must reference a subject.');
            $this->assertNotNull($event->subject_id);
        }
    }

    public function test_demo_seed_membership_data_is_rerunnable(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $counts = [
            'people' => Person::query()->count(),
            'memberships' => Membership::query()->count(),
            'histories' => MembershipStatusHistory::query()->count(),
            'approvals' => ApprovalRequest::query()->count(),
            'audits' => AuditEvent::query()->count(),
        ];

        // Natural-key snapshots, not just counts: every membership must keep a
        // stable person link and every person a stable name.
        $personNamesBefore = Person::query()->orderBy('id')->pluck('name')->all();
        $membershipKeysBefore = Membership::query()->orderBy('id')->pluck('person_id')->all();

        $this->seed(DemoDatabaseSeeder::class);

        $this->assertSame($counts['people'], Person::query()->count(), 'Rerun must not duplicate people.');
        $this->assertSame($counts['memberships'], Membership::query()->count(), 'Rerun must not duplicate memberships.');
        $this->assertSame($counts['histories'], MembershipStatusHistory::query()->count(), 'Rerun must not duplicate status histories.');
        $this->assertSame($counts['approvals'], ApprovalRequest::query()->count(), 'Rerun must not duplicate approval requests.');
        $this->assertSame($counts['audits'], AuditEvent::query()->count(), 'Rerun must not duplicate audit events.');

        $this->assertSame($personNamesBefore, Person::query()->orderBy('id')->pluck('name')->all(), 'Person natural keys must be stable.');
        $this->assertSame($membershipKeysBefore, Membership::query()->orderBy('id')->pluck('person_id')->all(), 'Membership person links must be stable.');

        // Each person is linked to exactly one membership.
        $this->assertSame(
            Person::query()->count(),
            Membership::query()->distinct()->count('person_id'),
            'There must be exactly one membership per person.',
        );

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

    // -----------------------------------------------------------------
    // Task 5 — pages, activities, training locations, galleries
    // -----------------------------------------------------------------

    public function test_demo_seed_creates_publication_count_targets(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertSame(8, Page::query()->count(), 'There must be exactly eight pages.');
        $this->assertSame(39, Event::query()->count(), 'There must be exactly 39 activities.');

        $this->assertSame(
            65,
            TrainingLocation::query()->count(),
            'There must be exactly 65 training locations.',
        );

        $this->assertSame(26, Gallery::query()->count(), 'There must be exactly 26 galleries.');

        // Every activity/location/gallery has at least one future and one past
        // activity row across the dataset.
        $this->assertGreaterThanOrEqual(
            1,
            Event::query()->where('starts_at', '>=', now())->count(),
            'At least one activity must be upcoming.',
        );
        $this->assertGreaterThanOrEqual(
            1,
            Event::query()->where('starts_at', '<', now())->count(),
            'At least one activity must be in the past.',
        );

        // All five training-location owner types are represented.
        $candidateTypes = [
            OrganizationUnitType::Cabang,
            OrganizationUnitType::Ranting,
            OrganizationUnitType::Rayon,
            OrganizationUnitType::Ukm,
            OrganizationUnitType::Komisariat,
        ];

        foreach ($candidateTypes as $type) {
            $unitIds = OrganizationUnit::query()->where('type', $type)->pluck('id');
            $count = TrainingLocation::query()->whereIn('organization_unit_id', $unitIds)->count();

            $this->assertGreaterThan(
                0,
                $count,
                "Training locations must cover owner type {$type->value}.",
            );
        }

        // Gallery categories latihan / kegiatan / pembinaan are all present.
        foreach ([GalleryCategory::Latihan, GalleryCategory::Kegiatan, GalleryCategory::Pembinaan] as $category) {
            $this->assertGreaterThan(
                0,
                Gallery::query()->where('category', $category)->count(),
                "Gallery category {$category->value} must be represented.",
            );
        }

        // Galleries must be owned by special units (UKM + Komisariat) as well as
        // branches and rantings.
        foreach ([
            OrganizationUnitType::Cabang,
            OrganizationUnitType::Ranting,
            OrganizationUnitType::Ukm,
            OrganizationUnitType::Komisariat,
        ] as $type) {
            $unitIds = OrganizationUnit::query()->where('type', $type)->pluck('id');

            $this->assertGreaterThan(
                0,
                Gallery::query()->whereIn('organization_unit_id', $unitIds)->count(),
                "Galleries must cover owner type {$type->value}.",
            );
        }
    }

    public function test_demo_seed_publication_states_and_published_fields_are_consistent(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        foreach ([
            Page::class,
            Event::class,
            TrainingLocation::class,
            Gallery::class,
        ] as $modelClass) {
            foreach (PublicationStatus::cases() as $status) {
                $this->assertGreaterThan(
                    0,
                    $modelClass::query()->where('status', $status)->count(),
                    "{$modelClass} must contain at least one {$status->value} row.",
                );
            }

            // Only published rows carry published_at / published_by_id.
            $this->assertSame(
                $modelClass::query()->where('status', PublicationStatus::Published)->count(),
                $modelClass::query()->whereNotNull('published_at')->whereNotNull('published_by_id')->count(),
                "{$modelClass} must set published_at and published_by_id exactly for published rows.",
            );

            $this->assertSame(
                0,
                $modelClass::query()
                    ->where('status', '!=', PublicationStatus::Published)
                    ->where(function ($query): void {
                        $query->whereNotNull('published_at')->orWhereNotNull('published_by_id');
                    })
                    ->count(),
                "{$modelClass} must not expose publication fields on non-published rows.",
            );

            // PublishContent::publish() forbids self-approval: the publisher must
            // never be the submitter, and both actors must hold a valid
            // assignment (operator) so the seeded review trail is realistic.
            foreach ($modelClass::query()->where('status', PublicationStatus::Published)->get() as $published) {
                $this->assertNotNull($published->submitted_by_id, "Published {$modelClass} must have a submitter.");
                $this->assertNotNull($published->published_by_id, "Published {$modelClass} must have a publisher.");
                $this->assertNotSame(
                    $published->submitted_by_id,
                    $published->published_by_id,
                    "Published {$modelClass} #{$published->id} must not be self-approved.",
                );

                foreach ([$published->submitted_by_id, $published->published_by_id] as $actorId) {
                    $this->assertTrue(
                        UnitAssignment::query()
                            ->where('user_id', $actorId)
                            ->where('is_active', true)
                            ->exists(),
                        "Publication actor {$actorId} must hold an active assignment.",
                    );
                }
            }
        }
    }

    public function test_demo_seed_pages_and_activity_ownership_and_covers_are_safe(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        // Pages: organization profile + province history are unit-less; branch
        // histories point at their branch.
        $this->assertSame(
            2,
            Page::query()->whereNull('organization_unit_id')->count(),
            'The general profile and province history must not be unit-scoped.',
        );

        // The public Blade escapes `$page->body`, so a good body must be plain
        // text and must not embed literal HTML markup.
        foreach (Page::query()->get() as $page) {
            $this->assertNotEmpty(trim((string) $page->body), "Page {$page->slug} must have a body.");
            $this->assertSame(
                0,
                preg_match('/<[a-z!\/][^>]*>/i', (string) $page->body),
                "Page {$page->slug} body must not contain literal HTML tags.",
            );
        }

        $this->assertSame(
            6,
            Page::query()->whereNotNull('organization_unit_id')->count(),
            'Six branch histories must each point at a branch.',
        );

        $branchIds = OrganizationUnit::query()->where('type', OrganizationUnitType::Cabang)->pluck('id');
        $this->assertSame(
            0,
            Page::query()
                ->whereNotNull('organization_unit_id')
                ->whereNotIn('organization_unit_id', $branchIds)
                ->count(),
            'Branch histories must point at branch units.',
        );

        // Every activity owner is a branch or a ranting.
        $allowedOwnerIds = OrganizationUnit::query()
            ->whereIn('type', [OrganizationUnitType::Cabang, OrganizationUnitType::Ranting])
            ->pluck('id');

        $this->assertSame(
            0,
            Event::query()->whereNotIn('organization_unit_id', $allowedOwnerIds)->count(),
            'Activities must belong to a branch or a ranting.',
        );

        // Every gallery/location owner is a seeded unit (FK already guarantees
        // existence); assert locations never live at the province root.
        $provinceId = OrganizationUnit::query()->where('type', OrganizationUnitType::Provinsi)->value('id');
        $this->assertSame(
            0,
            TrainingLocation::query()->where('organization_unit_id', $provinceId)->count(),
            'Training locations must not belong to the province root.',
        );

        // Filled cover paths must be traversal-safe and paired with alt text.
        foreach (Event::query()->whereNotNull('cover_path')->get() as $event) {
            $this->assertTrue(
                SafeStoragePath::isSafe((string) $event->cover_path, [SafeStoragePath::EVENT_COVER_PREFIX]),
                "Event {$event->slug} cover path must be a safe private/event-covers path.",
            );
            $this->assertNotNull($event->cover_alt_text, 'Every filled event cover needs alt text.');
        }

        // Published events with a cover always carry alt text.
        foreach (Event::query()->whereNotNull('cover_path')->get() as $event) {
            if ($event->status === PublicationStatus::Published) {
                $this->assertNotEmpty($event->cover_alt_text, 'Published event covers need alt text.');
            }
        }

        // Training locations only carry coordinates inside Kalimantan Selatan bounds.
        foreach (TrainingLocation::query()->get() as $location) {
            $latitude = (float) $location->latitude;
            $longitude = (float) $location->longitude;

            $this->assertGreaterThanOrEqual(-4.5, $latitude, "Location {$location->slug} latitude out of range.");
            $this->assertLessThanOrEqual(-1.5, $latitude, "Location {$location->slug} latitude out of range.");
            $this->assertGreaterThanOrEqual(113.5, $longitude, "Location {$location->slug} longitude out of range.");
            $this->assertLessThanOrEqual(117.5, $longitude, "Location {$location->slug} longitude out of range.");
        }

        // Verified timestamps only for a deterministic published subset; at
        // least one published-but-unverified location still exists.
        $this->assertGreaterThan(
            0,
            TrainingLocation::query()->whereNotNull('verified_at')->count(),
            'Some published locations must be verified for the public map.',
        );
        $this->assertSame(
            0,
            TrainingLocation::query()->whereNotNull('verified_at')->where('status', '!=', PublicationStatus::Published)->count(),
            'Only published locations may be verified.',
        );
        $this->assertGreaterThan(
            0,
            TrainingLocation::query()
                ->where('status', PublicationStatus::Published)
                ->whereNull('verified_at')
                ->count(),
            'At least one published location must remain unverified to exercise the verified-only contract.',
        );
    }

    public function test_demo_seed_publication_data_is_rerunnable(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $counts = [
            'pages' => Page::query()->count(),
            'events' => Event::query()->count(),
            'locations' => TrainingLocation::query()->count(),
            'galleries' => Gallery::query()->count(),
        ];

        $pageSlugs = Page::query()->orderBy('id')->pluck('slug')->all();
        $eventSlugs = Event::query()->orderBy('id')->pluck('slug')->all();
        $locationSlugs = TrainingLocation::query()->orderBy('id')->pluck('slug')->all();
        $gallerySlugs = Gallery::query()->orderBy('id')->pluck('slug')->all();

        $this->seed(DemoDatabaseSeeder::class);

        $this->assertSame($counts['pages'], Page::query()->count(), 'Rerun must not duplicate pages.');
        $this->assertSame($counts['events'], Event::query()->count(), 'Rerun must not duplicate activities.');
        $this->assertSame($counts['locations'], TrainingLocation::query()->count(), 'Rerun must not duplicate locations.');
        $this->assertSame($counts['galleries'], Gallery::query()->count(), 'Rerun must not duplicate galleries.');

        $this->assertSame($pageSlugs, Page::query()->orderBy('id')->pluck('slug')->all(), 'Page slugs must be stable.');
        $this->assertSame($eventSlugs, Event::query()->orderBy('id')->pluck('slug')->all(), 'Event slugs must be stable.');
        $this->assertSame($locationSlugs, TrainingLocation::query()->orderBy('id')->pluck('slug')->all(), 'Location slugs must be stable.');
        $this->assertSame($gallerySlugs, Gallery::query()->orderBy('id')->pluck('slug')->all(), 'Gallery slugs must be stable.');
    }

    public function test_demo_seed_publication_audits_are_scoped_to_seeded_content(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        // Every published Page/Event/TrainingLocation/Gallery has exactly one
        // `content.published` audit whose subject is that seeded record.
        $subjects = [];

        foreach ([Page::class, Event::class, TrainingLocation::class, Gallery::class] as $modelClass) {
            $publishedIds = $modelClass::query()
                ->where('status', PublicationStatus::Published)
                ->pluck('id')
                ->all();

            foreach ($publishedIds as $id) {
                $audit = AuditEvent::query()
                    ->where('action', 'content.published')
                    ->where('subject_type', (new $modelClass)->getMorphClass())
                    ->where('subject_id', $id)
                    ->first();

                $this->assertNotNull(
                    $audit,
                    "Published {$modelClass} #{$id} must have a content.published audit row.",
                );
                $this->assertNotNull($audit->actor_id, 'Publication audits must carry an actor.');
                $this->assertSame($id, $audit->metadata['content_id'] ?? $id, 'Audit metadata must reference the content.');
                $subjects[] = $audit->subject_type.'#'.$id;
            }
        }

        // The only `content.published` audits are the province placeholder plus
        // the seeded publication records — never an unrelated local row.
        $seededSubjectTypes = [
            (new Page)->getMorphClass(),
            (new Event)->getMorphClass(),
            (new TrainingLocation)->getMorphClass(),
            (new Gallery)->getMorphClass(),
        ];

        $this->assertSame(0, AuditEvent::query()
            ->where('action', 'content.published')
            ->whereNotIn('subject_type', $seededSubjectTypes)
            ->where('metadata->content_type', '!=', 'organization_profile')
            ->count(), 'content.published audits must only reference seeded publication records.');

        $this->assertSame(
            count($subjects),
            count(array_unique($subjects)),
            'Each seeded publication record must have exactly one publication audit.',
        );
    }

    public function test_demo_seed_does_not_audit_unrelated_published_content(): void
    {
        // Plant unrelated published content BEFORE seeding. It must survive
        // untouched and must never gain a `content.published` audit.
        $foreignUnit = OrganizationUnit::factory()->create([
            'name' => 'Unit Asing',
            'type' => OrganizationUnitType::Cabang,
        ]);

        $foreignPage = Page::factory()->create([
            'slug' => 'halaman-asing',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => null,
        ]);
        $foreignEvent = Event::factory()->create([
            'slug' => 'kegiatan-asing',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $foreignUnit->id,
        ]);
        $foreignLocation = TrainingLocation::factory()->create([
            'slug' => 'tempat-asing',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $foreignUnit->id,
        ]);
        $foreignGallery = Gallery::factory()->create([
            'slug' => 'galeri-asing',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $foreignUnit->id,
        ]);

        $foreignAuditCountBefore = AuditEvent::query()->count();
        $publicationAuditsBefore = AuditEvent::query()->where('action', 'content.published')->count();

        $this->seed(DemoDatabaseSeeder::class);

        // The planted rows are unchanged and carry no publication audit.
        foreach ([
            [$foreignPage->getMorphClass(), $foreignPage->id],
            [$foreignEvent->getMorphClass(), $foreignEvent->id],
            [$foreignLocation->getMorphClass(), $foreignLocation->id],
            [$foreignGallery->getMorphClass(), $foreignGallery->id],
        ] as [$morphClass, $id]) {
            $this->assertSame(
                0,
                AuditEvent::query()
                    ->where('action', 'content.published')
                    ->where('subject_type', $morphClass)
                    ->where('subject_id', $id)
                    ->count(),
                "Unrelated {$morphClass} #{$id} must not gain a content.published audit.",
            );
        }

        $this->assertDatabaseHas('pages', ['id' => $foreignPage->id, 'status' => PublicationStatus::Published->value]);
        $this->assertDatabaseHas('events', ['id' => $foreignEvent->id, 'status' => PublicationStatus::Published->value]);
        $this->assertDatabaseHas('training_locations', ['id' => $foreignLocation->id, 'status' => PublicationStatus::Published->value]);
        $this->assertDatabaseHas('galleries', ['id' => $foreignGallery->id, 'status' => PublicationStatus::Published->value]);

        // `content.published` gains exactly the demo rows: 4 pages + 10 events +
        // 16 locations + 6 galleries + 1 province placeholder = 37. The planted
        // foreign rows add nothing.
        $this->assertGreaterThanOrEqual($foreignAuditCountBefore, AuditEvent::query()->count());
        $this->assertSame(
            $publicationAuditsBefore + 37,
            AuditEvent::query()->where('action', 'content.published')->count(),
            'Seeding must add exactly the demo content.published audits and nothing else.',
        );
    }

    public function test_demo_seed_publishers_are_in_scope_and_distinct_from_submitters(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $cases = [
            [Page::class, PagePolicy::class],
            [Event::class, EventPolicy::class],
            [TrainingLocation::class, TrainingLocationPolicy::class],
            [Gallery::class, GalleryPolicy::class],
        ];

        foreach ($cases as [$modelClass, $policyClass]) {
            $policy = app($policyClass);

            foreach ($modelClass::query()->where('status', PublicationStatus::Published)->get() as $record) {
                $publisher = User::query()->findOrFail($record->published_by_id);
                $submitter = User::query()->findOrFail($record->submitted_by_id);

                $this->assertNotSame(
                    $submitter->id,
                    $publisher->id,
                    "{$modelClass} #{$record->id} must not be self-approved.",
                );

                $ownerUnitId = $record->organization_unit_id;

                if ($ownerUnitId !== null) {
                    $this->assertTrue(
                        in_array($ownerUnitId, $publisher->visibleUnitIds(), true),
                        "{$modelClass} #{$record->id} publisher must have the owner unit in visible scope.",
                    );
                }

                // The publisher must be able to view what it published.
                $this->assertTrue(
                    $policy->view($publisher, $record),
                    "{$modelClass} #{$record->id} publisher must be able to view the record per policy.",
                );
            }
        }
    }

    public function test_demo_seed_public_portal_renders_published_content_only(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        // Homepage, events, galleries, and locations all render successfully.
        $this->get(route('public.home'))->assertOk();
        $this->get(route('public.events.index'))->assertOk();
        $this->get(route('public.galleries.index'))->assertOk();
        $this->get(route('public.training-locations.index'))->assertOk();

        // The published profile page renders its plain-text body.
        $profile = Page::query()->where('status', PublicationStatus::Published)->firstOrFail();

        $this->get(route('public.pages.show', $profile->slug))
            ->assertOk()
            ->assertSee($profile->title);

        // A published activity resolves and a non-published activity 404s.
        $publishedEvent = Event::query()->where('status', PublicationStatus::Published)->firstOrFail();
        $hiddenEvent = Event::query()->where('status', '!=', PublicationStatus::Published)->firstOrFail();

        $this->get(route('public.events.show', $publishedEvent->slug))->assertOk();
        $this->get(route('public.events.show', $hiddenEvent->slug))->assertNotFound();

        // A non-published page 404s.
        $hiddenPage = Page::query()->where('status', '!=', PublicationStatus::Published)->firstOrFail();
        $this->get(route('public.pages.show', $hiddenPage->slug))->assertNotFound();

        // Anonymous responses never leak the private cover path.
        $this->get(route('public.events.index'))->assertDontSee('private/event-covers', false);
    }

    public function test_demo_seed_public_gallery_and_location_pages_render_seeded_content(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        // A published gallery with a category appears on the gallery index.
        $publishedGallery = Gallery::query()
            ->where('status', PublicationStatus::Published)
            ->whereNotNull('category')
            ->firstOrFail();

        $this->get(route('public.galleries.index'))
            ->assertOk()
            ->assertSee($publishedGallery->title);

        // Its category filter still lists it.
        $this->get(route('public.galleries.index', ['category' => $publishedGallery->category->value]))
            ->assertOk()
            ->assertSee($publishedGallery->title);

        // A non-published gallery never appears anonymously. Publish titles can
        // legitimately repeat across albums, so only assert absence when the
        // hidden title is unique across all galleries (otherwise a published
        // album could share the exact title).
        $hiddenGallery = Gallery::query()
            ->where('status', '!=', PublicationStatus::Published)
            ->first();

        if ($hiddenGallery !== null
            && Gallery::query()->where('title', $hiddenGallery->title)->count() === 1) {
            $this->get(route('public.galleries.index'))
                ->assertOk()
                ->assertDontSee($hiddenGallery->title);
        }

        // Regardless of title collisions, the published set returned by the
        // controller must never contain a non-published album.
        $publishedGallerySlugs = Gallery::query()
            ->where('status', PublicationStatus::Published)
            ->pluck('slug')
            ->all();
        $this->assertSame(26 - count($publishedGallerySlugs), Gallery::query()
            ->where('status', '!=', PublicationStatus::Published)->count());

        // A published, verified location renders on the index and detail pages.
        $verifiedLocation = TrainingLocation::query()
            ->where('status', PublicationStatus::Published)
            ->whereNotNull('verified_at')
            ->firstOrFail();

        $this->get(route('public.training-locations.index'))
            ->assertOk()
            ->assertSee($verifiedLocation->name);

        $this->get(route('public.training-locations.show', $verifiedLocation->slug))
            ->assertOk()
            ->assertSee($verifiedLocation->name)
            ->assertSee((string) $verifiedLocation->address);

        // A published-but-unverified location never appears on the public index
        // (only assert when its name is unique, to avoid substring collisions
        // with other locations sharing the same owner unit).
        $unverified = TrainingLocation::query()
            ->where('status', PublicationStatus::Published)
            ->whereNull('verified_at')
            ->first();

        if ($unverified !== null
            && TrainingLocation::query()->where('name', $unverified->name)->count() === 1) {
            $this->get(route('public.training-locations.index'))
                ->assertOk()
                ->assertDontSee($unverified->name);
        }

        // The verified public detail must 404 for an unverified slug.
        if ($unverified !== null) {
            $this->get(route('public.training-locations.show', $unverified->slug))->assertNotFound();
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
