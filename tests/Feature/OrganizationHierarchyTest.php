<?php

namespace Tests\Feature;

use App\Actions\CreateOrganizationUnit;
use App\Models\OrganizationUnit;
use App\OrganizationUnitType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganizationHierarchyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{OrganizationUnitType, OrganizationUnitType}>
     */
    public static function validParentTypes(): array
    {
        return [
            'branch under province' => [OrganizationUnitType::Cabang, OrganizationUnitType::Provinsi],
            'sub-branch under branch' => [OrganizationUnitType::Ranting, OrganizationUnitType::Cabang],
            'rayon under sub-branch' => [OrganizationUnitType::Rayon, OrganizationUnitType::Ranting],
            'university under province' => [OrganizationUnitType::Ukm, OrganizationUnitType::Provinsi],
            'commissariat under province' => [OrganizationUnitType::Komisariat, OrganizationUnitType::Provinsi],
        ];
    }

    #[DataProvider('validParentTypes')]
    public function test_creates_unit_under_its_valid_formal_parent(
        OrganizationUnitType $childType,
        OrganizationUnitType $parentType,
    ): void {
        $parent = OrganizationUnit::factory()->create(['type' => $parentType]);

        $unit = app(CreateOrganizationUnit::class)->handle([
            'name' => "Unit {$childType->value}",
            'type' => $childType,
            'parent_id' => $parent->id,
        ]);

        $this->assertTrue($unit->parent->is($parent));
    }

    public function test_rejects_unit_under_invalid_formal_parent(): void
    {
        $ranting = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Ranting]);

        $this->expectException(ValidationException::class);

        app(CreateOrganizationUnit::class)->handle([
            'name' => 'Cabang Tidak Sah',
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $ranting->id,
        ]);
    }

    public function test_allows_only_one_province_root(): void
    {
        OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);

        $this->expectException(ValidationException::class);

        app(CreateOrganizationUnit::class)->handle([
            'name' => 'Provinsi Kedua',
            'type' => OrganizationUnitType::Provinsi,
            'parent_id' => null,
        ]);
    }

    public function test_rejects_formal_children_for_university_unit(): void
    {
        $ukm = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Ukm]);

        $this->expectException(ValidationException::class);

        app(CreateOrganizationUnit::class)->handle([
            'name' => 'Ranting di bawah UKM',
            'type' => OrganizationUnitType::Ranting,
            'parent_id' => $ukm->id,
        ]);
    }

    public function test_allows_university_unit_to_reference_branch_as_supervisor(): void
    {
        $province = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $branch = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $province->id,
        ]);

        $unit = app(CreateOrganizationUnit::class)->handle([
            'name' => 'UKM Kampus',
            'type' => OrganizationUnitType::Ukm,
            'parent_id' => $province->id,
            'supervising_branch_id' => $branch->id,
        ]);

        $this->assertTrue($unit->supervisingBranch->is($branch));
    }

    public function test_rejects_non_branch_as_university_supervisor(): void
    {
        $province = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $ranting = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Ranting,
            'parent_id' => OrganizationUnit::factory()->create([
                'type' => OrganizationUnitType::Cabang,
                'parent_id' => $province->id,
            ])->id,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateOrganizationUnit::class)->handle([
            'name' => 'UKM Salah Pembina',
            'type' => OrganizationUnitType::Ukm,
            'parent_id' => $province->id,
            'supervising_branch_id' => $ranting->id,
        ]);
    }
}
