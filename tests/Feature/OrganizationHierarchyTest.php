<?php

namespace Tests\Feature;

use App\Actions\CreateOrganizationUnit as CreateOrganizationUnitAction;
use App\Filament\Resources\OrganizationUnits\Pages\CreateOrganizationUnit;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
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
            'university under branch' => [OrganizationUnitType::Ukm, OrganizationUnitType::Cabang],
            'commissariat under branch' => [OrganizationUnitType::Komisariat, OrganizationUnitType::Cabang],
        ];
    }

    #[DataProvider('validParentTypes')]
    public function test_creates_unit_under_its_valid_formal_parent(
        OrganizationUnitType $childType,
        OrganizationUnitType $parentType,
    ): void {
        $parent = OrganizationUnit::factory()->create(['type' => $parentType]);

        $unit = app(CreateOrganizationUnitAction::class)->handle([
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

        app(CreateOrganizationUnitAction::class)->handle([
            'name' => 'Cabang Tidak Sah',
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $ranting->id,
        ]);
    }

    public function test_allows_only_one_province_root(): void
    {
        OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);

        $this->expectException(ValidationException::class);

        app(CreateOrganizationUnitAction::class)->handle([
            'name' => 'Provinsi Kedua',
            'type' => OrganizationUnitType::Provinsi,
            'parent_id' => null,
        ]);
    }

    public function test_creates_special_units_under_branch_with_branch_parent_id(): void
    {
        $province = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $branch = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $province->id,
        ]);

        $ukm = app(CreateOrganizationUnitAction::class)->handle([
            'name' => 'UKM Kampus Banjarmasin',
            'type' => OrganizationUnitType::Ukm,
            'parent_id' => $branch->id,
        ]);
        $komisariat = app(CreateOrganizationUnitAction::class)->handle([
            'name' => 'Komisariat Banjarbaru',
            'type' => OrganizationUnitType::Komisariat,
            'parent_id' => $branch->id,
        ]);

        $this->assertSame($branch->id, $ukm->parent_id);
        $this->assertSame($branch->id, $komisariat->parent_id);
        $this->assertTrue($ukm->parent->is($branch));
        $this->assertTrue($komisariat->parent->is($branch));
        $this->assertFalse($ukm->type->mayHaveChildren());
        $this->assertFalse($komisariat->type->mayHaveChildren());
    }

    public function test_rejects_rayon_under_special_units(): void
    {
        $province = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $branch = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $province->id,
        ]);
        $ukm = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Ukm,
            'parent_id' => $branch->id,
        ]);
        $komisariat = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Komisariat,
            'parent_id' => $branch->id,
        ]);

        foreach ([$ukm, $komisariat] as $specialUnit) {
            try {
                app(CreateOrganizationUnitAction::class)->handle([
                    'name' => "Rayon di bawah {$specialUnit->type->value}",
                    'type' => OrganizationUnitType::Rayon,
                    'parent_id' => $specialUnit->id,
                ]);

                $this->fail("Expected ValidationException for a rayon under {$specialUnit->type->value}.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('parent_id', $exception->errors());
            }
        }
    }

    public function test_allows_university_unit_to_reference_branch_as_supervisor(): void
    {
        $province = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $branch = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $province->id,
        ]);

        $unit = app(CreateOrganizationUnitAction::class)->handle([
            'name' => 'UKM Kampus',
            'type' => OrganizationUnitType::Ukm,
            'parent_id' => $branch->id,
            'supervising_branch_id' => $branch->id,
        ]);

        $this->assertTrue($unit->parent->is($branch));
        $this->assertTrue($unit->supervisingBranch->is($branch));
    }

    public function test_rejects_non_branch_as_university_supervisor(): void
    {
        $province = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $branch = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $province->id,
        ]);
        $ranting = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Ranting,
            'parent_id' => $branch->id,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateOrganizationUnitAction::class)->handle([
            'name' => 'UKM Salah Pembina',
            'type' => OrganizationUnitType::Ukm,
            'parent_id' => $branch->id,
            'supervising_branch_id' => $ranting->id,
        ]);
    }

    public function test_filament_form_parent_options_follow_the_selected_type_contract(): void
    {
        $province = OrganizationUnit::factory()->create([
            'name' => 'Pengurus Provinsi Kalimantan Selatan',
            'type' => OrganizationUnitType::Provinsi,
        ]);
        $branch = OrganizationUnit::factory()->create([
            'name' => 'Cabang Banjarmasin',
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $province->id,
        ]);
        $ranting = OrganizationUnit::factory()->create([
            'name' => 'Ranting Banjarmasin Selatan',
            'type' => OrganizationUnitType::Ranting,
            'parent_id' => $branch->id,
        ]);

        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $province->id,
        ]);
        $this->actingAs($operator);

        $page = new CreateOrganizationUnit;
        $form = $page->form;
        $parentSelect = $form->getComponent('parent_id');

        foreach ([
            [OrganizationUnitType::Ukm, [$branch->id => $branch->name]],
            [OrganizationUnitType::Komisariat, [$branch->id => $branch->name]],
            [OrganizationUnitType::Ranting, [$branch->id => $branch->name]],
            [OrganizationUnitType::Rayon, [$ranting->id => $ranting->name]],
            [OrganizationUnitType::Cabang, [$province->id => $province->name]],
            [OrganizationUnitType::Provinsi, []],
        ] as [$type, $expectedOptions]) {
            $form->getComponent('type')->state($type->value);

            $this->assertSame(
                $expectedOptions,
                $parentSelect->getOptions(),
                "Unexpected formal-parent options for {$type->value}.",
            );
        }
    }

    public function test_filament_form_parent_options_stay_within_operator_unit_scope(): void
    {
        $province = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $assignedBranch = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $province->id,
        ]);
        $siblingProvince = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $outsideBranch = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $siblingProvince->id,
        ]);

        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $assignedBranch->id,
        ]);
        $this->actingAs($operator);

        $page = new CreateOrganizationUnit;
        $form = $page->form;
        $form->getComponent('type')->state(OrganizationUnitType::Ukm->value);

        $options = $form->getComponent('parent_id')->getOptions();

        // The assigned branch sees itself as the only in-scope Cabang parent;
        // the sibling province's branch stays hidden.
        $this->assertSame([$assignedBranch->id => $assignedBranch->name], $options);
        $this->assertArrayNotHasKey($outsideBranch->id, $options);
    }
}
