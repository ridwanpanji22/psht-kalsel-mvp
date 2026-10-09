<?php

namespace Tests\Feature;

use App\Models\OrganizationPosition;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\OrganizationUnitType;
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
