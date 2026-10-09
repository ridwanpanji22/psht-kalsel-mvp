<?php

namespace Database\Seeders;

use App\Models\OrganizationUnit;
use App\OrganizationUnitType;
use Database\Seeders\Support\DemoSeedContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Seeds the deterministic demo organization tree for PSHT Kalimantan Selatan.
 *
 * Shape: one province root -> 13 branches -> (5 rantings, 1 UKM, 1 Komisariat)
 * with 2-5 rayons per ranting. Every record is resolved by its natural tuple
 * (name, type, parent_id) so reruns update instead of duplicating.
 */
class DemoOrganizationSeeder extends Seeder
{
    /** Rayon counts applied per ranting index, cycling to 16 rayons per branch. */
    private const RAYON_PATTERN = [2, 3, 4, 5, 2];

    /** @var list<string> */
    private const BRANCH_NAMES = [
        'Banjarmasin',
        'Banjarbaru',
        'Martapura',
        'Pelaihari',
        'Kandangan',
        'Barabai',
        'Amuntai',
        'Tanjung',
        'Kotabaru',
        'Batulicin',
        'Rantau',
        'Marabahan',
        'Paringin',
    ];

    /** @var list<string> */
    private const RANTING_NAMES = [
        'Selatan',
        'Utara',
        'Timur',
        'Barat',
        'Tengah',
    ];

    public function run(DemoSeedContext $context): void
    {
        $this->seedProvince($context);

        foreach (self::BRANCH_NAMES as $index => $branchName) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $branchKey = "branch-{$number}";

            $branch = $this->upsertUnit(
                name: "Cabang {$branchName}",
                type: OrganizationUnitType::Cabang,
                parentId: $context->province()->id,
            );
            $context->putUnit($branchKey, $branch);

            $this->seedRantings($context, $branch, $branchKey);
            $this->seedSpecialUnits($context, $branch, $branchKey);
        }
    }

    private function seedProvince(DemoSeedContext $context): void
    {
        $demoProvinceName = 'Pengurus Provinsi Kalimantan Selatan';

        // Refuse to run against a database that already holds a *different*
        // province root. The domain allows exactly one formal province, and the
        // "no global delete / no reparent" rule forbids cleaning up a foreign
        // root, so the safest action is to stop before writing anything.
        $foreignProvinceExists = OrganizationUnit::query()
            ->where('type', OrganizationUnitType::Provinsi)
            ->where('name', '!=', $demoProvinceName)
            ->exists();

        if ($foreignProvinceExists) {
            throw new RuntimeException(
                'Seeder demo dihentikan: database sudah memiliki unit provinsi lain. '.
                'Jalankan pada database demo yang bersih atau reset terlebih dahulu.',
            );
        }

        $province = $this->upsertUnit(
            name: $demoProvinceName,
            type: OrganizationUnitType::Provinsi,
            parentId: null,
        );

        $context->setProvince($province);
        $context->putUnit('province-kalimantan-selatan', $province);
    }

    private function seedRantings(DemoSeedContext $context, OrganizationUnit $branch, string $branchKey): void
    {
        foreach (self::RANTING_NAMES as $index => $rantingName) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $rantingKey = "ranting-{$branchKey}-{$number}";

            $ranting = $this->upsertUnit(
                name: "Ranting {$rantingName} {$branch->name}",
                type: OrganizationUnitType::Ranting,
                parentId: $branch->id,
            );
            $context->putUnit($rantingKey, $ranting);

            $this->seedRayons($context, $ranting, $rantingKey, self::RAYON_PATTERN[$index]);
        }
    }

    private function seedRayons(
        DemoSeedContext $context,
        OrganizationUnit $ranting,
        string $rantingKey,
        int $count,
    ): void {
        for ($position = 1; $position <= $count; $position++) {
            $number = str_pad((string) $position, 2, '0', STR_PAD_LEFT);
            $rayonKey = "rayon-{$rantingKey}-{$number}";

            $rayon = $this->upsertUnit(
                name: "Rayon {$number} {$ranting->name}",
                type: OrganizationUnitType::Rayon,
                parentId: $ranting->id,
            );
            $context->putUnit($rayonKey, $rayon);
        }
    }

    private function seedSpecialUnits(DemoSeedContext $context, OrganizationUnit $branch, string $branchKey): void
    {
        $ukm = $this->upsertUnit(
            name: "UKM {$branch->name}",
            type: OrganizationUnitType::Ukm,
            parentId: $branch->id,
            supervisingBranchId: $branch->id,
        );
        $context->putUnit("special-ukm-{$branchKey}", $ukm);

        // Komisariat demonstrates the relation WITHOUT a supervising branch:
        // supervising metadata is optional and must never be implied.
        $komisariat = $this->upsertUnit(
            name: "Komisariat {$branch->name}",
            type: OrganizationUnitType::Komisariat,
            parentId: $branch->id,
            supervisingBranchId: null,
        );
        $context->putUnit("special-komisariat-{$branchKey}", $komisariat);
    }

    private function upsertUnit(
        string $name,
        OrganizationUnitType $type,
        ?int $parentId,
        ?int $supervisingBranchId = null,
    ): OrganizationUnit {
        return OrganizationUnit::query()->updateOrCreate(
            [
                'name' => $name,
                'type' => $type,
                'parent_id' => $parentId,
            ],
            [
                'supervising_branch_id' => $supervisingBranchId,
                'is_active' => true,
            ],
        );
    }
}
