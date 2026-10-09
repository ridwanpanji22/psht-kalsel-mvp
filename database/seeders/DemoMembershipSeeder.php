<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\OrganizationUnit;
use App\Models\Person;
use App\Models\User;
use App\OrganizationUnitType;
use App\Support\OrganizationScope;
use Database\Seeders\Support\DemoSeedContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the deterministic synthetic membership dataset.
 *
 * Produces exactly 1,800 memberships spread across the province, branches,
 * rantings, rayons, UKM, and Komisariat with roughly 60% siswa / 40% warga.
 * Direct Eloquent writes inside a single transaction are used instead of the
 * actor-scoped create actions because 1,800 action calls would be needlessly
 * slow and would couple seed ordering to UI validation. The same observable
 * status-history and audit rows those actions produce are written explicitly.
 *
 * No NIK, credential, or real identity is generated: names, birth places, and
 * member numbers are all synthetic and deterministic.
 */
class DemoMembershipSeeder extends Seeder
{
    /** Total deterministic memberships. */
    public const TOTAL = 1800;

    /** Memberships whose current status is not active. */
    public const STATUS_VARIANT_TOTAL = 200;

    /** Warga conversion approval requests. */
    public const CONVERSION_TOTAL = 30;

    public const CONVERSION_PENDING = 10;

    public const CONVERSION_APPROVED = 10;

    public const CONVERSION_REJECTED = 10;

    /** Number of distinct approval statuses cycled across conversion requests. */
    private const CONVERSION_STATUSES = 3;

    /** Deterministic timestamps keep the dataset reproducible. */
    private const REVIEWED_AT = '2026-09-15 10:00:00';

    /** Cycle of aktivasi stages so the seeded dataset yields varied started_on values. */
    private const YEAR_CYCLE = ['2014', '2015', '2017', '2018', '2021', '2023', '2024', '2026'];

    /** @var list<string> */
    private const FIRST_NAMES = [
        'Ahmad', 'Budi', 'Citra', 'Dewi', 'Eko', 'Fajar', 'Gita', 'Hadi',
        'Indra', 'Joko', 'Kartika', 'Lina', 'Maya', 'Nanda', 'Oki', 'Putri',
    ];

    /** @var list<string> */
    private const LAST_NAMES = [
        'Saputra', 'Wijaya', 'Rahman', 'Hidayat', 'Setiawan', 'Nugroho', 'Pratama', 'Kusuma',
    ];

    /** @var list<string> */
    private const BIRTH_PLACES = [
        'Banjarmasin', 'Banjarbaru', 'Martapura', 'Pelaihari', 'Kandangan',
        'Barabai', 'Amuntai', 'Tanjung', 'Kotabaru',
    ];

    /**
     * Branch names in seeded order, used to map a branch back to its
     * deterministic number (01..13).
     *
     * @var list<string>
     */
    private const BRANCH_NAMES = [
        'Banjarmasin', 'Banjarbaru', 'Martapura', 'Pelaihari', 'Kandangan',
        'Barabai', 'Amuntai', 'Tanjung', 'Kotabaru', 'Batulicin',
        'Rantau', 'Marabahan', 'Paringin',
    ];

    public function run(DemoSeedContext $context): void
    {
        $units = $this->memberUnits($context);

        /** @var array<int, int> $unitByIndex unitId assigned to each membership index. */
        $unitByIndex = [];

        /** @var array<int, int> $membershipIdByIndex membershipId for each membership index. */
        $membershipIdByIndex = [];

        /** @var array<string, User> $operatorsByUnitId resolved unit operators. */
        $operatorsByUnitId = [];

        $this->forEachContextOperator($context, function (User $operator) use (&$operatorsByUnitId): void {
            foreach ($operator->unitAssignments()->where('is_active', true)->pluck('organization_unit_id') as $unitId) {
                $operatorsByUnitId[(int) $unitId] = $operator;
            }
        });

        $fallbackOperator = $context->operator('operator-provinsi');

        // Warga conversions are deterministic and index-based; memberships that
        // will be converted (approved) must be created as warga from the start
        // so reruns never regress a converted membership back to siswa.
        $convertedIndices = $this->convertedIndices();

        DB::transaction(function () use ($units, $operatorsByUnitId, $fallbackOperator, $convertedIndices, &$unitByIndex, &$membershipIdByIndex): void {
            foreach ($this->chunked(range(1, self::TOTAL)) as $chunk) {
                foreach ($chunk as $index) {
                    $this->createMembership(
                        $index,
                        $units[$index % count($units)],
                        $operatorsByUnitId,
                        $fallbackOperator,
                        in_array($index, $convertedIndices, true),
                        $unitByIndex,
                        $membershipIdByIndex,
                    );
                }
            }
        });

        // Deterministic key groups, only using enum-valid transitions.
        $this->applyStatusVariants($operatorsByUnitId, $fallbackOperator, $unitByIndex, $membershipIdByIndex);

        // Approval requests are built after statuses settle so converted
        // memberships do not conflict with the archived/stopped groups.
        $this->seedWargaConversions($context, $unitByIndex, $membershipIdByIndex);
    }

    /**
     * Resolve a unit from the context by its id using the indexed unit sets.
     */
    private function unitFor(DemoSeedContext $context, int $unitId): OrganizationUnit
    {
        if ($context->province()->id === $unitId) {
            return $context->province();
        }

        foreach ([...$context->branches(), ...$context->rantings(), ...$context->rayons(), ...$context->specialUnits()] as $unit) {
            if ($unit->id === $unitId) {
                return $unit;
            }
        }

        throw new \LogicException("Unit demo dengan id [{$unitId}] tidak ditemukan pada konteks.");
    }

    /**
     * @param  array<int, User>  $operatorsByUnitId
     * @param  array<int, int>  $unitByIndex
     * @param  array<int, int>  $membershipIdByIndex
     */
    private function createMembership(
        int $index,
        OrganizationUnit $unit,
        array $operatorsByUnitId,
        User $fallbackOperator,
        bool $isConvertedWarga,
        array &$unitByIndex,
        array &$membershipIdByIndex,
    ): void {
        $key = $this->memberKey($index);
        $type = $isConvertedWarga || $index % 5 === 0
            ? MembershipType::Warga
            : MembershipType::Siswa;
        $memberNumber = $type === MembershipType::Warga ? $this->wargaNumber($index) : null;

        $person = Person::query()->updateOrCreate(
            ['name' => $this->personName($index)],
            [
                'birth_place' => self::BIRTH_PLACES[$index % count(self::BIRTH_PLACES)],
                'birth_date' => $this->birthDate($index),
            ],
        );

        $membership = Membership::query()->updateOrCreate(
            ['person_id' => $person->id],
            [
                'organization_unit_id' => $unit->id,
                'type' => $type,
                'status' => MembershipStatus::Active,
                'member_number' => $memberNumber,
                'started_on' => $this->startedOn($index),
                'archived_at' => null,
            ],
        );

        $operator = $operatorsByUnitId[$unit->id] ?? $fallbackOperator;

        $membership->statusHistories()->updateOrCreate(
            ['from_status' => null, 'to_status' => MembershipStatus::Active, 'reason' => 'created'],
            ['actor_id' => $operator->id],
        );

        AuditEvent::query()->updateOrCreate(
            [
                'subject_type' => $membership->getMorphClass(),
                'subject_id' => $membership->id,
                'action' => 'membership.created',
            ],
            [
                'actor_id' => $operator->id,
                'metadata' => ['type' => $type->value, 'seed_key' => $key],
            ],
        );

        $unitByIndex[$index] = $unit->id;
        $membershipIdByIndex[$index] = (int) $membership->id;
    }

    /**
     * Iterate the context operators through a callback without exposing internals.
     *
     * @param  callable(User): void  $callback
     */
    private function forEachContextOperator(DemoSeedContext $context, callable $callback): void
    {
        foreach ($context->operators() as $operator) {
            $callback($operator);
        }
    }

    /**
     * Split a range into bounded chunks to keep the insert loop memory-flat.
     *
     * @param  list<int>  $range
     * @return list<list<int>>
     */
    private function chunked(array $range, int $size = 300): array
    {
        return array_chunk($range, $size);
    }

    /**
     * Ordered units used to distribute memberships, weighted toward lower units.
     *
     * @return list<OrganizationUnit>
     */
    private function memberUnits(DemoSeedContext $context): array
    {
        $units = [$context->province()];

        foreach ($context->branches() as $branch) {
            $units[] = $branch;
        }
        foreach ($context->rantings() as $ranting) {
            $units[] = $ranting;
        }
        foreach ($context->rayons() as $rayon) {
            $units[] = $rayon;
        }
        foreach ($context->specialUnits() as $special) {
            $units[] = $special;
        }

        return $units;
    }

    /**
     * Apply non-active statuses to a deterministic subset, writing the same
     * history and audit rows the ChangeMembershipStatus action would produce.
     *
     * @param  array<int, User>  $operatorsByUnitId
     * @param  array<int, int>  $unitByIndex
     * @param  array<int, int>  $membershipIdByIndex
     */
    private function applyStatusVariants(
        array $operatorsByUnitId,
        User $fallbackOperator,
        array $unitByIndex,
        array $membershipIdByIndex,
    ): void {
        for ($variant = 0; $variant < self::STATUS_VARIANT_TOTAL; $variant++) {
            $index = self::TOTAL - $variant;
            $membership = Membership::query()->findOrFail($membershipIdByIndex[$index]);
            $operator = $operatorsByUnitId[$unitByIndex[$index]] ?? $fallbackOperator;

            match ($variant % 4) {
                // active -> archived (direct, enum-valid).
                0 => $this->applyTransition($membership, $operator, MembershipStatus::Archived, 'demo_status_archived'),
                // active -> stopped.
                1 => $this->applyTransition($membership, $operator, MembershipStatus::Stopped, 'demo_status_stopped'),
                // active -> inactive.
                2 => $this->applyTransition($membership, $operator, MembershipStatus::Inactive, 'demo_status_inactive'),
                // active -> stopped -> archived (two-step valid path).
                default => $this->applyStoppedThenArchived($membership, $operator),
            };
        }
    }

    private function applyTransition(
        Membership $membership,
        User $operator,
        MembershipStatus $target,
        string $reason,
    ): void {
        $from = $membership->status;

        $membership->update([
            'status' => $target,
            'archived_at' => $target === MembershipStatus::Archived ? now() : null,
        ]);

        $this->writeTransition($membership, $operator, $from, $target, $reason);
    }

    private function applyStoppedThenArchived(Membership $membership, User $operator): void
    {
        $from = $membership->status;

        $membership->update(['status' => MembershipStatus::Stopped, 'archived_at' => null]);
        $this->writeTransition($membership, $operator, $from, MembershipStatus::Stopped, 'demo_status_stopped');

        $membership->update(['status' => MembershipStatus::Archived, 'archived_at' => now()]);
        $this->writeTransition($membership, $operator, MembershipStatus::Stopped, MembershipStatus::Archived, 'demo_status_archived');
    }

    private function writeTransition(
        Membership $membership,
        User $operator,
        MembershipStatus $from,
        MembershipStatus $to,
        string $reason,
    ): void {
        $membership->statusHistories()->updateOrCreate(
            [
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
            ],
            ['actor_id' => $operator->id],
        );

        AuditEvent::query()->updateOrCreate(
            [
                'subject_type' => $membership->getMorphClass(),
                'subject_id' => $membership->id,
                'action' => 'membership.status_changed',
                'metadata->from' => $from->value,
                'metadata->to' => $to->value,
            ],
            [
                'actor_id' => $operator->id,
                'metadata' => ['from' => $from->value, 'to' => $to->value],
            ],
        );
    }

    /**
     * Seed exactly 30 warga conversion requests: 10 pending, 10 approved,
     * 10 rejected, all on siswa memberships with unique payload numbers.
     *
     * @param  array<int, int>  $unitByIndex
     * @param  array<int, int>  $membershipIdByIndex
     */
    private function seedWargaConversions(
        DemoSeedContext $context,
        array $unitByIndex,
        array $membershipIdByIndex,
    ): void {
        $statuses = [
            ApprovalStatus::Pending,
            ApprovalStatus::Approved,
            ApprovalStatus::Rejected,
        ];

        // Deterministic, state-independent targets: a fixed block of member
        // indices below the status-variant range, so a rerun converts the same
        // memberships instead of drifting to whichever ones are still siswa.
        foreach ($this->conversionIndices() as $offset => $memberIndex) {
            $membership = Membership::query()->findOrFail($membershipIdByIndex[$memberIndex]);
            $status = $statuses[$offset % self::CONVERSION_STATUSES];
            $memberNumber = $this->wargaNumber($memberIndex);

            $unit = $this->unitFor($context, $unitByIndex[$memberIndex]);
            $requester = $this->requesterFor($context, $unit);
            $reviewer = $this->reviewerFor($context, $unit, $requester);

            $request = ApprovalRequest::query()->updateOrCreate(
                [
                    'membership_id' => $membership->id,
                    'type' => 'warga_conversion',
                ],
                $this->conversionAttributes($requester, $reviewer, $status, $memberNumber),
            );

            if ($status === ApprovalStatus::Approved) {
                $this->applyApprovedConversion($membership, $request, $reviewer, $memberNumber, $memberIndex);
            }
        }
    }

    /**
     * The deterministic member indices that receive a warga conversion request.
     *
     * Indices are chosen below the reserved variant range and are all true
     * siswa at creation (not part of the base warga ratio), so a conversion
     * always starts from a real siswa membership.
     *
     * @return list<int>
     */
    private function conversionIndices(): array
    {
        $start = self::TOTAL - self::STATUS_VARIANT_TOTAL - self::CONVERSION_TOTAL;
        $indices = [];

        for ($index = $start + 1; count($indices) < self::CONVERSION_TOTAL && $index <= self::TOTAL; $index++) {
            // Skip base-ratio warga memberships so conversions are siswa -> warga.
            if ($index % 5 === 0) {
                continue;
            }

            $indices[] = $index;
        }

        return $indices;
    }

    /**
     * The member indices whose conversion is approved, i.e. created as warga
     * from the start. Matches the offset pattern used by
     * {@see self::seedWargaConversions()} where every third offset is approved.
     *
     * @return list<int>
     */
    private function convertedIndices(): array
    {
        $converted = [];

        foreach ($this->conversionIndices() as $offset => $memberIndex) {
            if ($offset % self::CONVERSION_STATUSES === 1) {
                $converted[] = $memberIndex;
            }
        }

        return $converted;
    }

    /**
     * @return array<string, mixed>
     */
    private function conversionAttributes(
        User $requester,
        User $reviewer,
        ApprovalStatus $status,
        string $memberNumber,
    ): array {
        $attributes = [
            'requested_by_id' => $requester->id,
            'type' => 'warga_conversion',
            'status' => $status,
            'payload' => ['member_number' => $memberNumber],
            'review_note' => null,
            'reviewed_at' => null,
            'reviewed_by_id' => null,
        ];

        if ($status === ApprovalStatus::Approved) {
            $attributes['reviewed_by_id'] = $reviewer->id;
            $attributes['reviewed_at'] = self::REVIEWED_AT;
            $attributes['review_note'] = 'Disetujui pada rapat pengurus demo.';
        } elseif ($status === ApprovalStatus::Rejected) {
            $attributes['reviewed_by_id'] = $reviewer->id;
            $attributes['reviewed_at'] = self::REVIEWED_AT;
            $attributes['review_note'] = 'Ditolak: dokumen latihan belum lengkap.';
        }

        return $attributes;
    }

    private function applyApprovedConversion(
        Membership $membership,
        ApprovalRequest $request,
        User $reviewer,
        string $memberNumber,
        int $index,
    ): void {
        $membership->update([
            'type' => MembershipType::Warga,
            'member_number' => $memberNumber,
        ]);

        $membership->statusHistories()->updateOrCreate(
            [
                'from_status' => MembershipStatus::Active,
                'to_status' => MembershipStatus::Active,
                'reason' => 'warga_conversion_approved',
            ],
            ['actor_id' => $reviewer->id],
        );

        AuditEvent::query()->updateOrCreate(
            [
                'subject_type' => $membership->getMorphClass(),
                'subject_id' => $membership->id,
                'action' => 'membership.converted_to_warga',
            ],
            [
                'actor_id' => $reviewer->id,
                'metadata' => [
                    'approval_request_id' => $request->id,
                    'member_number' => $memberNumber,
                    'seed_key' => $this->memberKey($index),
                ],
            ],
        );
    }

    /**
     * Choose the requester for a conversion: the operator of the nearest branch
     * (or the province operator when the membership sits at province level).
     * This keeps the requester distinct from the unit operator who will review.
     */
    private function requesterFor(DemoSeedContext $context, OrganizationUnit $unit): User
    {
        $branch = $this->nearestBranch($unit);

        if ($branch === null) {
            return $context->operator('operator-provinsi');
        }

        return $context->operator('operator-branch-'.$this->branchNumber($branch));
    }

    /**
     * Choose a reviewer distinct from the requester that has the membership in
     * scope. Prefer the unit's own operator, then the branch operator, then the
     * province operator.
     *
     * Reviewer visibility follows {@see OrganizationScope}: an actor
     * sees their assigned unit and its descendants, so any operator assigned at
     * the unit or an ancestor of it can review.
     */
    private function reviewerFor(DemoSeedContext $context, OrganizationUnit $unit, User $requester): User
    {
        $candidates = [];

        $unitOperator = $this->operatorForUnitKey($context, $unit);

        if ($unitOperator !== null) {
            $candidates[] = $unitOperator;
        }

        $branch = $this->nearestBranch($unit);
        if ($branch !== null) {
            $candidates[] = $context->operator('operator-branch-'.$this->branchNumber($branch));
        }

        $candidates[] = $context->operator('operator-provinsi');

        foreach ($candidates as $candidate) {
            if ($candidate->id !== $requester->id) {
                return $candidate;
            }
        }

        return $context->operator('operator-provinsi');
    }

    /**
     * Resolve the unit's own operator by matching the context operator keys,
     * which follow the `operator-{unit key}` convention from DemoOperatorSeeder.
     */
    private function operatorForUnitKey(DemoSeedContext $context, OrganizationUnit $unit): ?User
    {
        foreach ($context->operators() as $key => $operator) {
            if ($this->operatorOwnsUnit($operator, $unit)) {
                return $operator;
            }
        }

        return null;
    }

    private function operatorOwnsUnit(User $operator, OrganizationUnit $unit): bool
    {
        return $operator->unitAssignments()
            ->where('is_active', true)
            ->where('organization_unit_id', $unit->id)
            ->exists();
    }

    private function nearestBranch(OrganizationUnit $unit): ?OrganizationUnit
    {
        $current = $unit;

        while ($current !== null) {
            if ($current->type === OrganizationUnitType::Cabang) {
                return $current;
            }

            $current = $current->parent;
        }

        return null;
    }

    /**
     * Derive the deterministic branch number from a branch name like "Cabang Banjarmasin".
     */
    private function branchNumber(OrganizationUnit $branch): string
    {
        $name = str_replace('Cabang ', '', $branch->name);
        $position = array_search($name, self::BRANCH_NAMES, true);

        return str_pad((string) (($position === false ? 0 : $position) + 1), 2, '0', STR_PAD_LEFT);
    }

    private function memberKey(int $index): string
    {
        return 'member-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT);
    }

    private function personName(int $index): string
    {
        $first = self::FIRST_NAMES[$index % count(self::FIRST_NAMES)];
        $last = self::LAST_NAMES[($index * 3) % count(self::LAST_NAMES)];

        return "{$first} {$last} Demo {$index}";
    }

    private function wargaNumber(int $index): string
    {
        return 'DEMO-W-'.str_pad((string) $index, 6, '0', STR_PAD_LEFT);
    }

    private function birthDate(int $index): string
    {
        $year = 1985 + ($index % 20);
        $month = str_pad((string) (1 + ($index % 12)), 2, '0', STR_PAD_LEFT);
        $day = str_pad((string) (1 + ($index % 27)), 2, '0', STR_PAD_LEFT);

        return "{$year}-{$month}-{$day}";
    }

    private function startedOn(int $index): string
    {
        $year = self::YEAR_CYCLE[$index % count(self::YEAR_CYCLE)];

        return "{$year}-08-01";
    }
}
