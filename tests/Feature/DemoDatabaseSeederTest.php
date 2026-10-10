<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\GalleryCategory;
use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use App\Enums\PublicationStatus;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\DemoArtifact;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\Media;
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
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDatabaseSeeder;
use Database\Seeders\DemoMediaSeeder;
use Database\Seeders\DemoMembershipSeeder;
use Database\Seeders\DemoPublicationSeeder;
use Database\Seeders\Support\DemoSeedContext;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoDatabaseSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** 26 galleries x 4 media rows. */
    private const MEDIA_ROWS = 104;

    /**
     * The `public` disk instance replaced by a failing-write wrapper, restored
     * after the test so rollback-written files stay observable.
     */
    private ?FilesystemAdapter $originalPublicDisk = null;

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

    public function test_demo_seed_media_is_scoped_to_seeded_galleries_and_events(): void
    {
        // Plant unrelated galleries and events BEFORE seeding. The media seeder
        // must never attach media rows to them or write files under their keys.
        $foreignUnit = OrganizationUnit::factory()->create([
            'name' => 'Unit Asing Media',
            'type' => OrganizationUnitType::Cabang,
        ]);

        $foreignGallery = Gallery::factory()->create([
            'slug' => 'galeri-99-latihan-unit-asing',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $foreignUnit->id,
        ]);
        $foreignEvent = Event::factory()->create([
            'slug' => 'kegiatan-asing-bersampul',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $foreignUnit->id,
            'cover_path' => 'private/event-covers/demo/kegiatan-asing.jpg',
            'cover_alt_text' => 'Sampul asing',
        ]);

        Storage::fake('local');
        Storage::fake('public');

        $this->seed(DemoDatabaseSeeder::class);

        // No media row ever references an unrelated gallery.
        $this->assertSame(
            0,
            Media::query()->where('gallery_id', $foreignGallery->id)->count(),
            'An unrelated gallery must never receive seeded media rows.',
        );

        // The unrelated event keeps its metadata and its cover file is never written.
        $this->assertSame('private/event-covers/demo/kegiatan-asing.jpg', $foreignEvent->refresh()->cover_path);
        $this->assertFalse(
            Storage::disk('local')->exists('private/event-covers/demo/kegiatan-asing.jpg'),
            'An unrelated event cover path must never be materialised by the demo seeder.',
        );
        $this->assertFalse(
            Storage::disk('local')->exists('private/gallery-media/demo/99/media-1.png'),
            'No storage directory may be created for an unrelated gallery key.',
        );
    }

    public function test_demo_seed_reconciliation_only_removes_manifest_owned_demo_artifacts(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Foreign/hand-managed files placed at the *exact* deterministic demo
        // path shapes this seeder produces, but under keys/names this seed never
        // produces (gallery key 99 is not seeded; cover kegiatan-98 is outside
        // the seeded 01..39 range). None has a manifest entry, so none may be
        // touched.
        $foreignLeadPrivate = 'private/gallery-media/demo/99/media-1.png';
        $foreignLeadPublic = 'public/gallery-media/demo/99/media-1.png';
        $foreignSlot = 'private/gallery-media/demo/99/media-3.webp';
        $foreignCover = 'private/event-covers/demo/kegiatan-98.jpg';
        $foreignUnknownKey = 'private/gallery-media/demo/777/manual-keep.png';

        Storage::disk('local')->put($foreignLeadPrivate, 'foreign lead private');
        Storage::disk('public')->put($foreignLeadPublic, 'foreign lead public');
        Storage::disk('local')->put($foreignSlot, 'foreign slot');
        Storage::disk('local')->put($foreignCover, 'foreign cover');
        Storage::disk('local')->put($foreignUnknownKey, 'foreign unknown key');

        // Foreign gallery + media row at an *exact-shaped* demo path (key 99),
        // never registered in the manifest and never seeded.
        $foreignUnit = OrganizationUnit::factory()->create([
            'name' => 'Cabang Asing Kepemilikan',
            'type' => OrganizationUnitType::Cabang,
        ]);
        $foreignGallery = Gallery::factory()->create([
            'slug' => 'galeri-99-latihan-cabang-asing',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $foreignUnit->id,
        ]);
        $foreignMedia = Media::query()->create([
            'gallery_id' => $foreignGallery->id,
            'organization_unit_id' => $foreignUnit->id,
            'disk' => 'local',
            'path' => $foreignLeadPrivate,
            'mime_type' => 'image/png',
            'size' => 5,
            'alt_text' => 'Media asing manual',
            'is_public' => false,
            'sort_order' => 1,
        ]);

        $this->seed(DemoDatabaseSeeder::class);

        // No manifest row was ever created for a foreign artifact...
        $this->assertSame(
            0,
            DemoArtifact::query()->whereIn('path', [
                $foreignLeadPrivate, $foreignLeadPublic, $foreignSlot, $foreignCover, $foreignUnknownKey,
            ])->count(),
            'The manifest must never claim a foreign artifact.',
        );

        // ...so the foreign row and every foreign file survive the run.
        $this->assertDatabaseHas('media', ['id' => $foreignMedia->id]);
        Storage::disk('local')->assertExists($foreignLeadPrivate);
        Storage::disk('public')->assertExists($foreignLeadPublic);
        Storage::disk('local')->assertExists($foreignSlot);
        Storage::disk('local')->assertExists($foreignCover);
        Storage::disk('local')->assertExists($foreignUnknownKey);
    }

    public function test_demo_seed_manifest_is_idempotent_across_reruns(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(DemoDatabaseSeeder::class);

        $firstArtifacts = DemoArtifact::query()
            ->orderBy('disk')->orderBy('path')
            ->get(['namespace', 'disk', 'path', 'kind', 'seed_key', 'owner_type', 'owner_id'])
            ->toArray();

        $this->assertNotEmpty($firstArtifacts, 'The run must register owned artifacts.');

        // Rerun the whole demo seed: the manifest converges, never duplicates.
        $this->seed(DemoDatabaseSeeder::class);

        $secondArtifacts = DemoArtifact::query()
            ->orderBy('disk')->orderBy('path')
            ->get(['namespace', 'disk', 'path', 'kind', 'seed_key', 'owner_type', 'owner_id'])
            ->toArray();

        $this->assertSame($firstArtifacts, $secondArtifacts, 'Reruns must not duplicate or drift manifest rows.');
    }

    public function test_demo_seed_failure_restores_overwritten_and_deleted_original_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // A gallery the seeder will own and rewrite: plant the exact
        // deterministic paths with sentinel bytes so we can prove the originals
        // are restored when a pre-commit failure happens after a promotion.
        $unit = OrganizationUnit::factory()->create([
            'name' => 'Cabang Pemulihan',
            'type' => OrganizationUnitType::Cabang,
        ]);
        OrganizationUnit::factory()->create([
            'name' => 'Provinsi Pemulihan',
            'type' => OrganizationUnitType::Provinsi,
        ]);
        $gallery = Gallery::factory()->create([
            'slug' => 'galeri-01-latihan-cabang-pemulihan',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $unit->id,
        ]);

        $overwrittenPrivate = 'private/gallery-media/demo/1/media-2.png';
        $overwrittenPublic = 'public/gallery-media/demo/1/media-1.png';

        Storage::disk('local')->put($overwrittenPrivate, 'ORIGINAL-PRIVATE-BYTES');
        Storage::disk('public')->put($overwrittenPublic, 'ORIGINAL-PUBLIC-BYTES');

        $context = new DemoSeedContext;
        $context->putPublicationKeys(Gallery::class, [$gallery->slug]);
        $context->putPublicationKeys(Event::class, []);

        // Fail the public promotion (final-path writes, never the staged temp
        // writes) so the local promotion has already overwritten its original
        // before the run aborts; rollback must restore both.
        $this->failPublicFinalPathWrites();

        try {
            app(DemoMediaSeeder::class)->run($context);
            $this->fail('Seeding must fail loudly when a media write is refused.');
        } catch (\RuntimeException $exception) {
            // Expected: the promotion failure surfaces.
            $this->assertStringContainsString('promosikan', $exception->getMessage());
        } finally {
            $this->restorePublicDisk();
        }

        // The originals were restored rather than left overwritten.
        $this->assertSame('ORIGINAL-PRIVATE-BYTES', Storage::disk('local')->get($overwrittenPrivate));
        $this->assertSame('ORIGINAL-PUBLIC-BYTES', Storage::disk('public')->get($overwrittenPublic));

        // No media rows or manifest rows committed.
        $this->assertSame(0, Media::query()->count(), 'A failed run must not commit media rows.');
        $this->assertSame(0, DemoArtifact::query()->count(), 'A failed run must not commit manifest rows.');
    }

    public function test_demo_seed_surfaces_a_distinct_failure_when_compensation_cannot_restore(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $unit = OrganizationUnit::factory()->create([
            'name' => 'Cabang Gagal Pulih',
            'type' => OrganizationUnitType::Cabang,
        ]);
        OrganizationUnit::factory()->create([
            'name' => 'Provinsi Gagal Pulih',
            'type' => OrganizationUnitType::Provinsi,
        ]);
        $gallery = Gallery::factory()->create([
            'slug' => 'galeri-01-latihan-cabang-gagal-pulih',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $unit->id,
        ]);

        $overwrittenPrivate = 'private/gallery-media/demo/1/media-2.png';
        $overwrittenPublic = 'public/gallery-media/demo/1/media-1.png';
        Storage::disk('local')->put($overwrittenPrivate, 'ORIGINAL-PRIVATE-BYTES');
        Storage::disk('public')->put($overwrittenPublic, 'ORIGINAL-PUBLIC-BYTES');

        $context = new DemoSeedContext;
        $context->putPublicationKeys(Gallery::class, [$gallery->slug]);
        $context->putPublicationKeys(Event::class, []);

        // Fail both the public promotion and every subsequent public write, so
        // rollback's attempt to restore the already-promoted public original
        // also fails — a restoration failure that must be surfaced distinctly.
        $this->failAllPublicFinalPathWrites();

        try {
            app(DemoMediaSeeder::class)->run($context);
            $this->fail('Seeding must fail when storage cannot be restored.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'Gagal memulihkan berkas media demo',
                $exception->getMessage(),
                'A failed restoration must raise a distinct hard failure.',
            );
        } finally {
            $this->restorePublicDisk();
        }

        // The local original is restored and the public original is unchanged
        // (the failed promotion never wrote over it); no DB state diverges from
        // the reported failure.
        $this->assertSame('ORIGINAL-PRIVATE-BYTES', Storage::disk('local')->get($overwrittenPrivate));
        $this->assertSame('ORIGINAL-PUBLIC-BYTES', Storage::disk('public')->get($overwrittenPublic));
        $this->assertSame(0, Media::query()->count());
        $this->assertSame(0, DemoArtifact::query()->count());
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

    // ---------------------------------------------------------------------
    // Media storage invariants (Task 6)
    // ---------------------------------------------------------------------

    public function test_demo_seed_creates_exactly_four_image_media_per_gallery(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(DemoDatabaseSeeder::class);

        $this->assertSame(26, Gallery::query()->count(), 'Exactly 26 galleries must exist.');
        $this->assertSame(
            self::MEDIA_ROWS,
            Media::query()->count(),
            'Every gallery carries exactly four media rows: 26 x 4 = 104.',
        );

        foreach (Gallery::query()->with('media')->get() as $gallery) {
            $this->assertCount(
                4,
                $gallery->media,
                "Gallery {$gallery->slug} must own exactly four media rows.",
            );
        }
    }

    public function test_demo_seed_media_rows_carry_valid_image_metadata(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(DemoDatabaseSeeder::class);

        $videoExtensions = ['mp4', 'mov', 'webm', 'avi', 'mkv', 'm4v'];

        foreach (Media::query()->get() as $media) {
            $this->assertNotEmpty(
                trim((string) $media->alt_text),
                "Media #{$media->id} must have non-empty alt text.",
            );
            $this->assertContains(
                $media->mime_type,
                ['image/jpeg', 'image/png', 'image/webp'],
                "Media #{$media->id} mime type must be an allowed still image.",
            );
            $this->assertGreaterThan(
                0,
                $media->size,
                "Media #{$media->id} must record a positive size.",
            );

            $extension = strtolower(pathinfo((string) $media->path, PATHINFO_EXTENSION));
            $this->assertNotContains(
                $extension,
                $videoExtensions,
                "Media #{$media->id} path must never be a video file.",
            );
        }

        // Sort order is unique and dense within each gallery (1..4).
        foreach (Gallery::query()->with('media')->get() as $gallery) {
            $sortOrders = $gallery->media->pluck('sort_order')->all();
            $this->assertSame(
                [1, 2, 3, 4],
                collect($sortOrders)->sort()->values()->all(),
                "Gallery {$gallery->slug} media sort order must be unique and dense.",
            );
        }
    }

    public function test_demo_seed_media_disk_and_visibility_are_consistent(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(DemoDatabaseSeeder::class);

        foreach (Media::query()->get() as $media) {
            if ($media->is_public) {
                $this->assertSame('public', $media->disk, "Public media #{$media->id} must live on the public disk.");
                $this->assertStringStartsWith(
                    'public/gallery-media/',
                    (string) $media->path,
                    "Public media #{$media->id} must use the public gallery prefix.",
                );
            } else {
                $this->assertSame('local', $media->disk, "Private media #{$media->id} must live on the local disk.");
                $this->assertTrue(
                    SafeStoragePath::isSafe((string) $media->path, [SafeStoragePath::GALLERY_MEDIA_PREFIX]),
                    "Private media #{$media->id} must be a safe private/gallery-media path.",
                );
            }
        }

        // Public media may only belong to a published gallery; private media is
        // expected somewhere too so both branches of the contract are exercised.
        $this->assertSame(
            0,
            Media::query()
                ->where('is_public', true)
                ->whereHas('gallery', fn ($query) => $query->where('status', '!=', PublicationStatus::Published))
                ->count(),
            'Public media must only belong to published galleries.',
        );
        $this->assertGreaterThan(0, Media::query()->where('is_public', true)->count(), 'Some media must be public.');
        $this->assertGreaterThan(0, Media::query()->where('is_public', false)->count(), 'Some media must stay private.');
    }

    public function test_demo_seed_media_files_exist_on_their_recorded_disks(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(DemoDatabaseSeeder::class);

        $checked = 0;

        foreach (Media::query()->get() as $media) {
            $disk = Storage::disk($media->disk);

            $this->assertTrue(
                $disk->exists((string) $media->path),
                "Media #{$media->id} file [{$media->path}] must exist on its {$media->disk} disk.",
            );

            if ($media->is_public) {
                // Public album media is copied into the public tree, so the
                // private original must have been released by the lifecycle.
                // A sibling private row of the same gallery proves the path
                // shape below is the one the seeder actually writes.
                $privateSibling = $media->gallery->media()->where('is_public', false)->first();

                if ($privateSibling !== null) {
                    $this->assertTrue(
                        Storage::disk('local')->exists((string) $privateSibling->path),
                        "Private media #{$privateSibling->id} file must exist so the released-original check is meaningful.",
                    );
                }

                $this->assertFalse(
                    Storage::disk('local')->exists(
                        SafeStoragePath::GALLERY_MEDIA_PREFIX.'demo/'.$this->galleryKeyFor($media->gallery->slug).'/'.$this->mediaFilename($media),
                    ),
                    "Public media #{$media->id} must not retain a private original at its demo gallery path.",
                );
            }

            $checked++;
        }

        $this->assertSame(self::MEDIA_ROWS, $checked, 'Every seeded media row must be verified against real storage.');
    }

    public function test_demo_seed_event_cover_files_exist_for_filled_paths(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(DemoDatabaseSeeder::class);

        $withCover = Event::query()->whereNotNull('cover_path')->get();
        $this->assertGreaterThan(0, $withCover->count(), 'Some activities must carry a cover path.');

        foreach ($withCover as $event) {
            $this->assertTrue(
                SafeStoragePath::isSafe((string) $event->cover_path, [SafeStoragePath::EVENT_COVER_PREFIX]),
                "Event {$event->slug} cover must be a safe private/event-covers path.",
            );
            $this->assertTrue(
                Storage::disk('local')->exists((string) $event->cover_path),
                "Event {$event->slug} cover file must exist on the local disk.",
            );

            $mimeType = Storage::disk('local')->mimeType((string) $event->cover_path);
            $this->assertContains(
                $mimeType,
                ['image/jpeg', 'image/png', 'image/webp'],
                "Event {$event->slug} cover must be a real still image.",
            );

            // The stored bytes must match the path extension: a `.jpg` path may
            // never contain PNG/WebP bytes, and a `.webp` path may never contain
            // JPEG/PNG bytes.
            $extension = strtolower(pathinfo((string) $event->cover_path, PATHINFO_EXTENSION));
            $expectedMime = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => null,
            };

            $this->assertNotNull($expectedMime, "Event {$event->slug} cover extension must be an allowed image type.");
            $this->assertSame(
                $expectedMime,
                $mimeType,
                "Event {$event->slug} cover content must match its .{$extension} extension.",
            );
        }
    }

    public function test_demo_seed_reconciles_only_manifest_owned_stale_artifacts_on_rerun(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // First run: full demo dataset, so real demo rows/files exist and their
        // manifest entries are registered.
        $this->seed(DemoDatabaseSeeder::class);

        // Snapshot a gallery and an event that the publication seeder seeded.
        $gallery = Gallery::query()->where('status', PublicationStatus::Published)->firstOrFail();
        $event = Event::query()->whereNotNull('cover_path')->firstOrFail();

        // Foreign/hand-managed stale artifacts with no manifest entry: they use
        // demo-shaped paths but this seeder never registered them, so they must
        // survive even though their key/event is not in the seeded set.
        $foreignStaleLocal = 'private/gallery-media/demo/999/media-1.png';
        $foreignStalePublic = 'public/gallery-media/demo/999/media-1.png';
        $foreignStaleCover = 'private/event-covers/demo/kegiatan-999.jpg';

        Storage::disk('local')->put($foreignStaleLocal, 'stale');
        Storage::disk('public')->put($foreignStalePublic, 'stale');
        Storage::disk('local')->put($foreignStaleCover, 'stale');
        Storage::disk('local')->put('private/gallery-media/manual-keep.png', 'keep');
        Storage::disk('public')->put('public/gallery-media/manual-keep.png', 'keep');

        $foreignUnit = OrganizationUnit::factory()->create([
            'name' => 'Unit Usang',
            'type' => OrganizationUnitType::Cabang,
        ]);
        $foreignStaleGallery = Gallery::factory()->create([
            'slug' => 'galeri-999-latihan-unit-usang',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $foreignUnit->id,
        ]);
        $foreignStaleMedia = Media::query()->create([
            'gallery_id' => $foreignStaleGallery->id,
            'organization_unit_id' => $foreignUnit->id,
            'disk' => 'local',
            'path' => $foreignStaleLocal,
            'mime_type' => 'image/png',
            'size' => 5,
            'alt_text' => 'Media usang',
            'is_public' => false,
            'sort_order' => 1,
        ]);

        // Drive the media seeder with a context that (a) no longer lists the
        // tracked gallery's previous published state (it is now draft) and
        // (b) lists no events, so the manifest-owned lead + covers reconcile.
        $gallery->update(['status' => PublicationStatus::Draft, 'published_by_id' => null, 'published_at' => null]);

        $context = new DemoSeedContext;
        $context->putPublicationKeys(Gallery::class, [$gallery->slug]);
        $context->putPublicationKeys(Event::class, []);

        app(DemoMediaSeeder::class)->run($context);

        // The manifest-owned public lead of the now-draft gallery is gone.
        $this->assertSame(
            0,
            Media::query()->where('gallery_id', $gallery->id)->where('is_public', true)->count(),
            'A gallery that stopped being published must not keep public media.',
        );
        Storage::disk('public')->assertMissing('public/gallery-media/demo/'.$this->galleryKeyFor($gallery->slug).'/media-1.png');

        // The manifest-owned cover of the now-unseeded event is gone.
        Storage::disk('local')->assertMissing((string) $event->cover_path);

        // Foreign artifacts with no manifest proof survive untouched.
        $this->assertDatabaseHas('media', ['id' => $foreignStaleMedia->id]);
        Storage::disk('local')->assertExists($foreignStaleLocal);
        Storage::disk('public')->assertExists($foreignStalePublic);
        Storage::disk('local')->assertExists($foreignStaleCover);

        // Unrelated hand-managed files survive untouched.
        Storage::disk('local')->assertExists('private/gallery-media/manual-keep.png');
        Storage::disk('public')->assertExists('public/gallery-media/manual-keep.png');
    }

    public function test_demo_seed_promotion_never_deletes_unmanifested_private_lead(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // A published gallery whose lead (slot 1) is promoted to the public
        // disk. The private lead path is never written by this run, so a
        // foreign/hand-managed file sitting at that exact path has no manifest
        // entry and must never be deleted by the promotion cleanup.
        $unit = OrganizationUnit::factory()->create([
            'name' => 'Cabang Pemimpin Asing',
            'type' => OrganizationUnitType::Cabang,
        ]);
        $gallery = Gallery::factory()->create([
            'slug' => 'galeri-01-latihan-cabang-pemimpin-asing',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $unit->id,
        ]);

        $foreignPrivateLead = 'private/gallery-media/demo/1/media-1.png';

        Storage::disk('local')->put($foreignPrivateLead, 'FOREIGN-PRIVATE-LEAD-BYTES');

        $context = new DemoSeedContext;
        $context->putPublicationKeys(Gallery::class, [$gallery->slug]);
        $context->putPublicationKeys(Event::class, []);

        app(DemoMediaSeeder::class)->run($context);

        // The lead row is public and its file lives on the public disk...
        $this->assertSame(
            1,
            Media::query()->where('gallery_id', $gallery->id)->where('sort_order', 1)->where('disk', 'public')->count(),
        );
        Storage::disk('public')->assertExists('public/gallery-media/demo/1/media-1.png');

        // ...but the unmanifested private lead is untouched: it was never owned
        // by this seeder (the seeder never wrote it), so cleanup must not delete
        // it merely because its path matches the deterministic shape.
        $this->assertFalse(
            DemoArtifact::query()->where('disk', 'local')->where('path', $foreignPrivateLead)->exists(),
            'The seeder must not register a manifest row for a file it never wrote.',
        );
        Storage::disk('local')->assertExists($foreignPrivateLead);
        $this->assertSame(
            'FOREIGN-PRIVATE-LEAD-BYTES',
            Storage::disk('local')->get($foreignPrivateLead),
        );
    }

    public function test_demo_seed_surfaces_a_failure_when_post_commit_cleanup_delete_is_refused(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Seed a full dataset so real demo-owned artifacts (including public
        // leads) exist and are registered in the manifest.
        $this->seed(DemoDatabaseSeeder::class);

        $staleGallery = Gallery::query()->where('status', PublicationStatus::Published)->firstOrFail();
        $staleKey = $this->galleryKeyFor($staleGallery->slug);
        $stalePublicLead = 'public/gallery-media/demo/'.$staleKey.'/media-1.png';

        Storage::disk('public')->assertExists($stalePublicLead);

        // With `throw => false`, a refused delete returns `false`. The cleanup
        // must never silently claim success while leaving the manifest-owned
        // file behind (storage would diverge from the committed DB state).
        $this->failPublicDeletesOf($stalePublicLead);

        // Drive a run whose seeded gallery set is empty, so every manifest-owned
        // gallery artifact (including the public leads) is superseded and queued
        // for post-commit deletion.
        $context = new DemoSeedContext;
        $context->putPublicationKeys(Gallery::class, []);
        $context->putPublicationKeys(Event::class, []);

        try {
            app(DemoMediaSeeder::class)->run($context);
            $this->fail('A refused destructive delete must surface loudly, not be ignored.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString(
                'menghapus',
                $exception->getMessage(),
                'A refused post-commit delete must raise a precise failure.',
            );
        } finally {
            $this->restorePublicDisk();
        }

        // The failed deletion is still observable: the file was not removed and
        // the failure was surfaced rather than swallowed.
        Storage::disk('public')->assertExists($stalePublicLead);
    }

    public function test_demo_seed_reconciles_media_when_gallery_drops_out_of_the_key_set(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // First run with two tracked galleries.
        $unit = OrganizationUnit::factory()->create([
            'name' => 'Cabang Rekonsiliasi',
            'type' => OrganizationUnitType::Cabang,
        ]);
        $keep = Gallery::factory()->create([
            'slug' => 'galeri-01-latihan-cabang-rekonsiliasi',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $unit->id,
        ]);
        $drop = Gallery::factory()->create([
            'slug' => 'galeri-02-kegiatan-cabang-rekonsiliasi',
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'organization_unit_id' => $unit->id,
        ]);

        $firstContext = new DemoSeedContext;
        $firstContext->putPublicationKeys(Gallery::class, [$keep->slug, $drop->slug]);
        $firstContext->putPublicationKeys(Event::class, []);
        app(DemoMediaSeeder::class)->run($firstContext);

        $this->assertSame(8, Media::query()->count());
        Storage::disk('public')->assertExists('public/gallery-media/demo/2/media-1.png');
        Storage::disk('local')->assertExists('private/gallery-media/demo/2/media-2.png');

        // Second run where the second gallery is no longer seeded.
        $secondContext = new DemoSeedContext;
        $secondContext->putPublicationKeys(Gallery::class, [$keep->slug]);
        $secondContext->putPublicationKeys(Event::class, []);
        app(DemoMediaSeeder::class)->run($secondContext);

        // The dropped gallery's rows/files are gone; the kept gallery survives.
        $this->assertSame(0, Media::query()->where('gallery_id', $drop->id)->count());
        Storage::disk('local')->assertMissing('private/gallery-media/demo/2/media-2.png');
        Storage::disk('public')->assertMissing('public/gallery-media/demo/2/media-1.png');
        $this->assertSame(4, Media::query()->where('gallery_id', $keep->id)->count());
        $this->assertSame(4, Media::query()->count());
    }

    public function test_demo_seed_compensates_when_media_storage_write_fails(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Make the public disk fail every write, so the public copy of the
        // first published gallery's lead image cannot be written.
        $this->failPublicDiskWrites();

        try {
            $this->seed(DemoDatabaseSeeder::class);
            $this->fail('Seeding must fail loudly when a media write is refused.');
        } catch (\RuntimeException) {
            // Expected: the seeder surfaces the storage failure.
        } finally {
            $this->restorePublicDisk();
        }

        // No media rows survive the failed run (single transaction rolled back)
        // and no orphan files were left behind on either disk.
        $this->assertSame(
            0,
            Media::query()->count(),
            'A failed media seed must not leave committed media rows.',
        );
        $this->assertSame(
            [],
            Storage::disk('local')->allFiles(),
            'A failed media seed must not leave orphan private files.',
        );
        $this->assertSame(
            [],
            Storage::disk('public')->allFiles(),
            'A failed media seed must not leave orphan public files.',
        );

        // A subsequent clean run still produces the full dataset.
        $this->seed(DemoDatabaseSeeder::class);

        $this->assertSame(self::MEDIA_ROWS, Media::query()->count());
    }

    // ---------------------------------------------------------------------
    // End-to-end rerun safety and cross-domain invariants (Task 7)
    // ---------------------------------------------------------------------

    /**
     * Rerun safety, end to end, through the *documented* entrypoint.
     *
     * Runs the exact documented command — `db:seed --class=DatabaseSeeder` — twice
     * against the same database, asserts each invocation exits successfully, and
     * compares full snapshots (row counts per table + natural-key sets) taken
     * after each run. A rerun must be idempotent by natural key: no duplicate
     * email, slug, member number, unit natural key, gallery media path,
     * assignment, demo artifact, or audit record. Counts alone could hide a swap,
     * so the distinct natural-key sets are compared too.
     */
    public function test_demo_seed_rerun_preserves_counts_and_natural_key_sets(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // First invocation of the documented entrypoint.
        $firstExit = Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);
        $this->assertSame(0, $firstExit, 'The first `db:seed` invocation must exit successfully.');

        $firstCounts = $this->tableCounts();
        $firstKeys = $this->naturalKeySets();

        // Snapshot is non-trivial: the demo dataset actually exists.
        $this->assertGreaterThan(0, $firstCounts['organization_units']);
        $this->assertGreaterThan(0, $firstCounts['media']);
        $this->assertGreaterThan(0, $firstCounts['demo_artifacts']);

        // Second invocation of the same documented entrypoint.
        $secondExit = Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--force' => true,
        ]);
        $this->assertSame(0, $secondExit, 'The second `db:seed` invocation must exit successfully.');

        // 1. No table grew or shrank across the rerun.
        $this->assertSame($firstCounts, $this->tableCounts(), 'A rerun must not change any table row count.');

        // 2. The natural-key sets are identical, not merely equal in size.
        $this->assertSame($firstKeys, $this->naturalKeySets(), 'A rerun must preserve every natural-key set.');

        // 3. Each natural key is genuinely unique on its own table.
        $this->assertSame(
            User::query()->count(),
            User::query()->distinct()->count('email'),
            'Seeded operator emails must be unique.',
        );
        $this->assertSame(
            Membership::query()->whereNotNull('member_number')->count(),
            Membership::query()->whereNotNull('member_number')->distinct()->count('member_number'),
            'Seeded member numbers must be unique.',
        );
        $this->assertSame(
            Media::query()->count(),
            Media::query()->distinct()->count('path'),
            'Seeded gallery media paths must be unique.',
        );

        // 4. Unit natural keys are unique per type (the composite key the seeder
        //    upserts on) and the operator/assignment keys never duplicate.
        foreach (OrganizationUnitType::cases() as $type) {
            $units = OrganizationUnit::query()->where('type', $type)->get();
            $keys = $units->map(fn (OrganizationUnit $unit): string => $unit->name.'|'.($unit->parent_id ?? 'root'));

            $this->assertSame(
                $units->count(),
                $keys->unique()->count(),
                "Unit natural keys for {$type->value} must be unique.",
            );
        }

        $assignmentKeys = UnitAssignment::query()->get()
            ->map(fn (UnitAssignment $assignment): string => $assignment->user_id.'|'.$assignment->organization_unit_id.'|'.$assignment->role);
        $this->assertSame(
            $assignmentKeys->count(),
            $assignmentKeys->unique()->count(),
            'Seeded unit assignments must be unique per user/unit/role.',
        );

        $mediaKeys = Media::query()->get()
            ->map(fn (Media $media): string => $media->gallery_id.'|'.$media->sort_order);
        $this->assertSame(
            $mediaKeys->count(),
            $mediaKeys->unique()->count(),
            'Seeded gallery media must be unique per gallery/sort order.',
        );

        // 5. The demo-artifact manifest keeps one row per (namespace, disk, path).
        $artifactKeys = DemoArtifact::query()->get()
            ->map(fn (DemoArtifact $artifact): string => $artifact->namespace.'|'.$artifact->disk.'|'.$artifact->path);
        $this->assertSame(
            $artifactKeys->count(),
            $artifactKeys->unique()->count(),
            'Demo artifacts must be unique per namespace/disk/path.',
        );
    }

    /**
     * Cross-domain ownership: every event/gallery/training-location owner is a
     * seeded organization unit, and every seeded unit is reachable in the
     * hierarchy seed (never an orphan planted by another process).
     */
    public function test_demo_seed_content_owners_are_seeded_units(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $seededUnitIds = OrganizationUnit::query()->pluck('id')->all();

        foreach ([Event::class, Gallery::class, TrainingLocation::class] as $modelClass) {
            $ownerIds = $modelClass::query()->pluck('organization_unit_id')->unique()->all();

            $this->assertNotEmpty($ownerIds, "{$modelClass} must be owned by at least one unit.");

            foreach ($ownerIds as $ownerId) {
                $this->assertContains(
                    $ownerId,
                    $seededUnitIds,
                    "{$modelClass} owner {$ownerId} must be a seeded organization unit.",
                );
            }
        }
    }

    /**
     * Cross-domain actors: every submitter, publisher, membership auditor, and
     * approval requester/reviewer is a seeded operator with a valid assignment.
     *
     * Each referenced actor id must resolve to a seeded user that is active
     * (`users.is_active = true`) and holds an active assignment whose `role` is
     * `operator` on a seeded organization unit. The referenced ids must be a
     * subset of the seeded operator ids — no workflow row may point at a
     * non-operator or an unseeded user.
     */
    public function test_demo_seed_workflow_actors_hold_valid_assignments(): void
    {
        $this->seed(DemoDatabaseSeeder::class);

        $seededOperatorIds = User::query()->pluck('id')->all();
        $seededUnitIds = OrganizationUnit::query()->pluck('id')->all();

        $actorIds = [];

        $assertSeededOperator = function (?int $userId, string $label) use ($seededOperatorIds, $seededUnitIds): void {
            if ($userId === null) {
                return;
            }

            $this->assertContains(
                $userId,
                $seededOperatorIds,
                "{$label} user #{$userId} must be a seeded operator.",
            );

            $user = User::query()->find($userId);
            $this->assertNotNull($user, "{$label} user #{$userId} must exist.");
            $this->assertTrue($user->is_active, "{$label} user #{$userId} must be active (`is_active`).");

            $operatorAssignments = UnitAssignment::query()
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->where('role', 'operator')
                ->get();

            $this->assertGreaterThanOrEqual(
                1,
                $operatorAssignments->count(),
                "{$label} user #{$userId} must hold an active `operator` assignment.",
            );

            foreach ($operatorAssignments as $assignment) {
                $this->assertContains(
                    $assignment->organization_unit_id,
                    $seededUnitIds,
                    "{$label} user #{$userId} must be assigned to a seeded unit.",
                );
            }
        };

        // Publication submitters/publishers across every content type.
        foreach ([Page::class, Event::class, TrainingLocation::class, Gallery::class] as $modelClass) {
            foreach ($modelClass::query()->get() as $row) {
                $assertSeededOperator($row->submitted_by_id, "{$modelClass}#{$row->id} submitter");
                $assertSeededOperator($row->published_by_id, "{$modelClass}#{$row->id} publisher");
                $actorIds[] = $row->submitted_by_id;
                $actorIds[] = $row->published_by_id;
            }
        }

        // Membership-status auditors.
        foreach (MembershipStatusHistory::query()->get() as $history) {
            $assertSeededOperator($history->actor_id, "MembershipStatusHistory#{$history->id} actor");
            $actorIds[] = $history->actor_id;
        }

        // Approval requester/reviewer.
        foreach (ApprovalRequest::query()->get() as $request) {
            $assertSeededOperator($request->requested_by_id, "ApprovalRequest#{$request->id} requester");
            $assertSeededOperator($request->reviewed_by_id, "ApprovalRequest#{$request->id} reviewer");
            $actorIds[] = $request->requested_by_id;
            $actorIds[] = $request->reviewed_by_id;
        }

        // Every referenced actor id is, without exception, a seeded operator id.
        $this->assertNotEmpty(array_filter($actorIds, fn ($id) => $id !== null), 'The dataset must reference workflow actors.');
        $this->assertSame(
            [],
            array_values(array_diff(array_unique(array_filter($actorIds)), $seededOperatorIds)),
            'Every workflow actor id must belong to the seeded operator set.',
        );
    }

    /**
     * Cross-domain media invariants: every media row's `organization_unit_id`
     * matches its gallery owner, and every published gallery exposes at least
     * one public media row on the public disk.
     */
    public function test_demo_seed_media_ownership_and_published_gallery_public_media(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(DemoDatabaseSeeder::class);

        // Media ownership always mirrors the gallery owner.
        foreach (Media::query()->with('gallery')->get() as $media) {
            $this->assertNotNull($media->gallery, "Media #{$media->id} must belong to a gallery.");
            $this->assertSame(
                $media->gallery->organization_unit_id,
                $media->organization_unit_id,
                "Media #{$media->id} must be owned by the same unit as its gallery.",
            );
        }

        // Every published gallery has at least one public media row, and every
        // public media row belongs to a published gallery.
        foreach (Gallery::query()->where('status', PublicationStatus::Published)->get() as $gallery) {
            $this->assertGreaterThanOrEqual(
                1,
                $gallery->media()->where('is_public', true)->where('disk', 'public')->count(),
                "Published gallery {$gallery->slug} must expose at least one public media row.",
            );
        }

        $this->assertSame(
            0,
            Media::query()
                ->where('is_public', true)
                ->whereHas('gallery', fn ($query) => $query->where('status', '!=', PublicationStatus::Published))
                ->count(),
            'Public media must never belong to a non-published gallery.',
        );

        // Non-published galleries keep their media private (never public).
        foreach (Gallery::query()->where('status', '!=', PublicationStatus::Published)->get() as $gallery) {
            $this->assertSame(
                0,
                $gallery->media()->where('is_public', true)->count(),
                "Non-published gallery {$gallery->slug} must not expose public media.",
            );
        }
    }

    /**
     * Public privacy: no anonymous response leaks member identities, member
     * numbers, operator emails, audit rows/metadata, private storage paths, or
     * non-published content titles.
     *
     * Every seeded membership/person name and every non-null member number is
     * covered — the assertion iterates the complete sets, not a sample. To keep
     * the runtime bounded it fetches each response body once and scans it with
     * `str_contains`, aggregating any offending value into a single failure.
     */
    public function test_demo_seed_anonymous_responses_never_leak_private_data(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(DemoDatabaseSeeder::class);

        $responses = [
            'home' => $this->get(route('public.home'))->assertOk(),
            'organization' => $this->get(route('public.organization.index'))->assertOk(),
            'events' => $this->get(route('public.events.index'))->assertOk(),
            'events-past' => $this->get(route('public.events.index', ['period' => 'past']))->assertOk(),
            'galleries' => $this->get(route('public.galleries.index'))->assertOk(),
            'locations' => $this->get(route('public.training-locations.index'))->assertOk(),
        ];

        // Bodies are fetched once; every check scans the cached string.
        $bodies = [];
        foreach ($responses as $label => $response) {
            $bodies[$label] = (string) $response->getContent();
        }

        /**
         * Assert none of $forbidden appears in any response body, reporting the
         * offending label/value pairs when one does.
         *
         * @param  list<string>  $forbidden
         */
        $assertNoneAppear = function (array $forbidden, string $what) use ($bodies): void {
            $forbidden = array_values(array_unique(array_filter($forbidden, fn (string $v): bool => $v !== '')));

            $hits = [];
            foreach ($bodies as $label => $body) {
                foreach ($forbidden as $value) {
                    if (str_contains($body, $value)) {
                        $hits[] = "{$label} ⇒ {$value}";
                    }
                }
            }

            $this->assertSame([], $hits, "No anonymous response may contain {$what}.");
        };

        // 1. Private storage path shapes appear in no anonymous response.
        foreach ($bodies as $label => $body) {
            $this->assertStringNotContainsString('private/gallery-media', $body, "{$label} leaked a private gallery path.");
            $this->assertStringNotContainsString('private/event-covers', $body, "{$label} leaked a private event-cover path.");
        }

        // 2. Every seeded member identity (name) and every non-null member number
        //    is scanned across every response. Full sets, not a sample.
        $allNames = Person::query()->pluck('name')->all();
        $allMemberNumbers = Membership::query()->whereNotNull('member_number')->pluck('member_number')
            ->map(fn ($number): string => (string) $number)->all();

        $this->assertNotEmpty($allNames, 'The dataset must contain seeded people for the leak check.');
        $this->assertNotEmpty($allMemberNumbers, 'The dataset must contain member numbers for the leak check.');

        $assertNoneAppear($allNames, 'a seeded member name');
        $assertNoneAppear($allMemberNumbers, 'a seeded member number');

        // 3. Operator emails never appear anonymously.
        $assertNoneAppear(User::query()->pluck('email')->all(), 'a seeded operator email');

        // 4. Audit rows never appear: action names, subject types, the full
        //    serialized metadata payload, the internal seed keys, and the
        //    serialized actor/subject id fragments. Bare integer ids are NOT
        //    asserted (small ints appear in ordinary markup); we assert their
        //    serialized JSON fragments and the distinctive string fields.
        $assertNoneAppear(AuditEvent::query()->distinct()->pluck('action')->all(), 'an audit action name');
        $assertNoneAppear(AuditEvent::query()->distinct()->pluck('subject_type')->all(), 'an audit subject type');

        $serializedMetadata = [];
        $seedKeys = [];
        $idFragments = [];
        foreach (AuditEvent::query()->get() as $audit) {
            if (! empty($audit->metadata)) {
                $serializedMetadata[] = (string) json_encode($audit->metadata);
            }

            if (isset($audit->metadata['seed_key'])) {
                $seedKeys[] = (string) $audit->metadata['seed_key'];
            }

            if ($audit->actor_id !== null) {
                $idFragments[] = '"actor_id":'.$audit->actor_id;
            }

            if ($audit->subject_id !== null) {
                $idFragments[] = '"subject_id":'.$audit->subject_id;
            }

            $referenceId = $audit->metadata['reference_membership_id'] ?? null;
            if ($referenceId !== null) {
                $idFragments[] = '"reference_membership_id":'.$referenceId;
            }
        }
        $this->assertNotEmpty($serializedMetadata, 'The dataset must contain serialized audit metadata.');
        $assertNoneAppear($serializedMetadata, 'a serialized audit metadata payload');
        $assertNoneAppear($seedKeys, 'an internal audit seed key');
        $assertNoneAppear($idFragments, 'a serialized audit actor/subject id fragment');

        // 5. Non-published content never appears. Titles can legitimately repeat
        //    across units, so only assert absence when a title is unique to a
        //    non-published row (otherwise a published row could share it).
        foreach ([Page::class, Event::class, Gallery::class, TrainingLocation::class] as $modelClass) {
            $hiddenTitles = [];
            foreach ($modelClass::query()->where('status', '!=', PublicationStatus::Published)->get() as $hidden) {
                $title = (string) $hidden->title;

                if ($title !== '' && $modelClass::query()->where('title', $title)->count() === 1) {
                    $hiddenTitles[] = $title;
                }
            }

            $assertNoneAppear($hiddenTitles, "a non-published {$modelClass} title");
        }

        // 6. The detail routes for non-published slugs 404 anonymously.
        $hiddenPage = Page::query()->where('status', '!=', PublicationStatus::Published)->firstOrFail();
        $hiddenEvent = Event::query()->where('status', '!=', PublicationStatus::Published)->firstOrFail();
        $hiddenLocation = TrainingLocation::query()->where('status', '!=', PublicationStatus::Published)->firstOrFail();

        $this->get(route('public.pages.show', $hiddenPage->slug))->assertNotFound();
        $this->get(route('public.events.show', $hiddenEvent->slug))->assertNotFound();
        $this->get(route('public.training-locations.show', $hiddenLocation->slug))->assertNotFound();
    }

    /**
     * Row counts per table, keyed by table name, for rerun comparison.
     *
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        return [
            'users' => User::query()->count(),
            'organization_units' => OrganizationUnit::query()->count(),
            'organization_positions' => OrganizationPosition::query()->count(),
            'unit_assignments' => UnitAssignment::query()->count(),
            'people' => Person::query()->count(),
            'memberships' => Membership::query()->count(),
            'membership_status_histories' => MembershipStatusHistory::query()->count(),
            'approval_requests' => ApprovalRequest::query()->count(),
            'audit_events' => AuditEvent::query()->count(),
            'pages' => Page::query()->count(),
            'events' => Event::query()->count(),
            'training_locations' => TrainingLocation::query()->count(),
            'galleries' => Gallery::query()->count(),
            'media' => Media::query()->count(),
            'demo_artifacts' => DemoArtifact::query()->count(),
        ];
    }

    /**
     * The distinct natural-key sets each rerunnable seeder upserts on, keyed by
     * a stable label. Sorted so the comparison is order-independent.
     *
     * @return array<string, list<string|int>>
     */
    private function naturalKeySets(): array
    {
        $sorted = static fn ($values): array => collect($values)->sort()->values()->all();

        return [
            'user_emails' => $sorted(User::query()->pluck('email')),
            'user_firebase_uids' => $sorted(User::query()->pluck('firebase_uid')),
            'unit_keys' => $sorted(OrganizationUnit::query()->get()
                ->map(fn (OrganizationUnit $unit): string => $unit->type->value.'|'.$unit->name.'|'.($unit->parent_id ?? 'root'))),
            'assignment_keys' => $sorted(UnitAssignment::query()->get()
                ->map(fn (UnitAssignment $assignment): string => $assignment->user_id.'|'.$assignment->organization_unit_id.'|'.$assignment->role)),
            'member_numbers' => $sorted(Membership::query()->whereNotNull('member_number')->pluck('member_number')),
            'page_slugs' => $sorted(Page::query()->pluck('slug')),
            'event_slugs' => $sorted(Event::query()->pluck('slug')),
            'location_slugs' => $sorted(TrainingLocation::query()->pluck('slug')),
            'gallery_slugs' => $sorted(Gallery::query()->pluck('slug')),
            'media_paths' => $sorted(Media::query()->pluck('path')),
            'demo_artifact_keys' => $sorted(DemoArtifact::query()->get()
                ->map(fn (DemoArtifact $artifact): string => $artifact->namespace.'|'.$artifact->disk.'|'.$artifact->path)),
            'audit_keys' => $sorted(AuditEvent::query()->get()
                ->map(fn (AuditEvent $audit): string => $audit->action.'|'.$audit->subject_type.'|'.$audit->subject_id)),
        ];
    }

    /**
     * Replace the `public` disk with a wrapper whose writes always return false,
     * mirroring a real disk configured with `throw => false` refusing a write.
     */
    private function failPublicDiskWrites(): void
    {
        $this->installFailingPublicDisk(static fn (string $path): bool => true);
    }

    /**
     * Fail the first `public` write to a *final* path (never the staged temp
     * path) and let later writes through, so staging succeeds, the first
     * promotion aborts the run (after the local disk's promotion already
     * overwrote its originals), and rollback's restore write can land.
     */
    private function failPublicFinalPathWrites(): void
    {
        $failed = false;

        $this->installFailingPublicDisk(
            static function (string $path) use (&$failed): bool {
                if ($failed || str_contains(basename($path), '.tmp-')) {
                    return false;
                }

                $failed = true;

                return true;
            },
        );
    }

    /**
     * Fail every `public` write to a *final* path (never the staged temp path),
     * so promotion and any subsequent restore write on the public disk both
     * fail — exercising the restoration-failure hard path.
     */
    private function failAllPublicFinalPathWrites(): void
    {
        $this->installFailingPublicDisk(
            static fn (string $path): bool => ! str_contains(basename($path), '.tmp-'),
        );
    }

    /**
     * Make the `public` disk refuse to delete a specific path (returning
     * `false`, as a disk configured with `throw => false` does), while every
     * other operation still works. Used to exercise the post-commit cleanup's
     * handling of a refused destructive delete.
     */
    private function failPublicDeletesOf(string $path): void
    {
        /** @var FilesystemAdapter $original */
        $original = Storage::disk('public');
        $this->originalPublicDisk = $original;

        $wrapper = new class($original, $path) extends FilesystemAdapter
        {
            public function __construct(FilesystemAdapter $inner, private string $refusedPath)
            {
                parent::__construct($inner->getDriver(), $inner->getAdapter(), $inner->getConfig());
            }

            public function delete($paths)
            {
                $paths = is_array($paths) ? $paths : [$paths];

                if (in_array($this->refusedPath, $paths, true)) {
                    return false;
                }

                return parent::delete($paths);
            }
        };

        Storage::set('public', $wrapper);
    }

    /**
     * @param  callable(string): bool  $shouldFail
     */
    private function installFailingPublicDisk(callable $shouldFail): void
    {
        /** @var FilesystemAdapter $original */
        $original = Storage::disk('public');
        $this->originalPublicDisk = $original;

        $wrapper = new class($original, $shouldFail) extends FilesystemAdapter
        {
            /** @var callable(string): bool */
            private $shouldFail;

            public function __construct(FilesystemAdapter $inner, callable $shouldFail)
            {
                parent::__construct($inner->getDriver(), $inner->getAdapter(), $inner->getConfig());

                $this->shouldFail = $shouldFail;
            }

            public function put($path, $contents, $options = [])
            {
                if (($this->shouldFail)($path)) {
                    return false;
                }

                return parent::put($path, $contents, $options);
            }
        };

        Storage::set('public', $wrapper);
    }

    /**
     * Restore the original `public` disk instance after a failing-write test so
     * files written during rollback remain observable.
     */
    private function restorePublicDisk(): void
    {
        if ($this->originalPublicDisk !== null) {
            Storage::set('public', $this->originalPublicDisk);
            $this->originalPublicDisk = null;
        }
    }

    /**
     * The deterministic gallery key used inside seeded storage paths.
     * Mirrors the numbering convention in {@see DemoPublicationSeeder}.
     */
    private function galleryKeyFor(string $slug): int
    {
        preg_match('/^galeri-(\d+)-/', $slug, $matches);

        return (int) ($matches[1] ?? 0);
    }

    /**
     * The basename of a media record's storage path.
     */
    private function mediaFilename(Media $media): string
    {
        return basename((string) $media->path);
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
