<?php

namespace Database\Seeders;

use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Database\Seeders\Support\DemoSeedContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds one active, onboarded demo operator per generated unit plus the
 * organization positions shown on the public portal.
 *
 * Every account shares the local-only demo password documented in README and
 * carries a deterministic dummy Firebase UID. The dummy UID deliberately cannot
 * authenticate against real Firebase; the guarded local demo login path is the
 * only way these accounts sign in.
 */
class DemoOperatorSeeder extends Seeder
{
    /** Local-only shared demo password. Never valid against production Firebase. */
    private const DEMO_PASSWORD = 'PshtKalsel@1922';

    /** Fixed onboarding timestamp keeps the dataset deterministic. */
    private const ONBOARDED_AT = '2026-09-01 08:00:00';

    /** Position titles cycled across the seeded units. */
    private const POSITION_TITLES = [
        'Ketua',
        'Sekretaris',
        'Bendahara',
    ];

    public function run(DemoSeedContext $context): void
    {
        $this->seedUnitOperators($context);
        $this->seedPositions($context);
    }

    /**
     * Create one operator per generated unit across all six unit types.
     */
    private function seedUnitOperators(DemoSeedContext $context): void
    {
        $province = $context->province();
        $this->upsertOperator($context, 'operator-provinsi', 'demo.provinsi@psht-kalsel.test', $province);

        foreach ($context->branches() as $key => $branch) {
            $this->upsertOperator($context, "operator-{$key}", $this->email('cabang', $key), $branch);
        }

        foreach ($context->rantings() as $key => $ranting) {
            $this->upsertOperator($context, "operator-{$key}", $this->email('ranting', $key), $ranting);
        }

        foreach ($context->rayons() as $key => $rayon) {
            $this->upsertOperator($context, "operator-{$key}", $this->email('rayon', $key), $rayon);
        }

        foreach ($context->specialUnits() as $key => $unit) {
            $this->upsertOperator($context, "operator-{$key}", $this->email('special', $key), $unit);
        }
    }

    /**
     * Create public current positions and a spread of historical (ended) ones.
     */
    private function seedPositions(DemoSeedContext $context): void
    {
        $province = $context->province();

        $this->upsertPosition(
            unit: $province,
            title: 'Ketua Pengurus Provinsi',
            holder: 'H. Ahmad Suryadi, S.H.',
            startsOn: '2024-01-01',
            isPublic: true,
        );

        $branchIndex = 0;
        foreach ($context->branches() as $branch) {
            $this->upsertPosition(
                unit: $branch,
                title: 'Ketua Cabang',
                holder: 'Pengurus Cabang '.$branch->name,
                startsOn: '2024-01-01',
                isPublic: true,
            );

            // A historical (ended) position for the first five branches only.
            if ($branchIndex < 5) {
                $this->upsertPosition(
                    unit: $branch,
                    title: 'Ketua Cabang',
                    holder: 'Pengurus Lama '.$branch->name,
                    startsOn: '2018-01-01',
                    endsOn: '2023-12-31',
                    isPublic: true,
                );
            }

            $branchIndex++;
        }

        // Non-public positions on a subset of special units to exercise filtering.
        $specialIndex = 0;
        foreach ($context->specialUnits() as $unit) {
            if ($specialIndex >= 4) {
                break;
            }

            $this->upsertPosition(
                unit: $unit,
                title: 'Pembina',
                holder: 'Pembina '.$unit->name,
                startsOn: '2024-01-01',
                isPublic: false,
            );

            $specialIndex++;
        }
    }

    private function upsertOperator(
        DemoSeedContext $context,
        string $contextKey,
        string $email,
        OrganizationUnit $unit,
    ): User {
        $uid = 'demo-operator-'.str_replace('operator-', '', $contextKey);

        $operator = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Operator '.$unit->name,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'is_active' => true,
                'firebase_uid' => $uid,
                'onboarded_at' => self::ONBOARDED_AT,
                'email_verified_at' => self::ONBOARDED_AT,
            ],
        );

        // Upsert by the unique (user_id, organization_unit_id) natural key.
        UnitAssignment::query()->updateOrCreate(
            [
                'user_id' => $operator->id,
                'organization_unit_id' => $unit->id,
            ],
            [
                'role' => 'operator',
                'is_active' => true,
            ],
        );

        $context->putOperator($contextKey, $operator);

        return $operator;
    }

    private function upsertPosition(
        OrganizationUnit $unit,
        string $title,
        string $holder,
        string $startsOn,
        ?string $endsOn = null,
        bool $isPublic = true,
    ): OrganizationPosition {
        // `starts_on` is date-cast, so Eloquent persists it as `Y-m-d 00:00:00`.
        // The natural-key search must use that exact stored representation, otherwise
        // reruns miss the existing row and duplicate it.
        return OrganizationPosition::query()->updateOrCreate(
            [
                'organization_unit_id' => $unit->id,
                'title' => $title,
                'starts_on' => $startsOn.' 00:00:00',
            ],
            [
                'holder_name' => $holder,
                'ends_on' => $endsOn,
                'is_public' => $isPublic,
            ],
        );
    }

    /**
     * Build a deterministic email from a unit context key.
     *
     * Examples: branch-01 -> demo.cabang.01@psht-kalsel.test,
     * ranting-branch-01-01 -> demo.ranting.branch-01-01@psht-kalsel.test.
     */
    private function email(string $type, string $key): string
    {
        $suffix = match ($type) {
            'cabang' => substr($key, strlen('branch-')),
            'ranting' => substr($key, strlen('ranting-')),
            'rayon' => substr($key, strlen('rayon-')),
            'special' => substr($key, strlen('special-')),
            default => $key,
        };

        return "demo.{$type}.{$suffix}@psht-kalsel.test";
    }
}
