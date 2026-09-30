<?php

namespace Tests\Feature;

use App\Actions\CreateUnitAssignment;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UnitAssignmentProvisioningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_operator_creates_operator_assignment_only_inside_formal_subtree(): void
    {
        $branch = OrganizationUnit::factory()->create();
        $ranting = OrganizationUnit::factory()->create(['parent_id' => $branch->id]);
        $operator = User::factory()->create();
        $newOperator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $branch->id,
            'role' => 'operator',
        ]);

        $assignment = app(CreateUnitAssignment::class)->handle($operator, [
            'user_id' => $newOperator->id,
            'organization_unit_id' => $ranting->id,
            'role' => 'operator',
        ]);

        $this->assertSame('operator', $assignment->role);
        $this->assertSame($ranting->id, $assignment->organization_unit_id);
    }

    public function test_operator_cannot_assign_a_role_above_operator(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateUnitAssignment::class)->handle($operator, [
            'user_id' => User::factory()->create()->id,
            'organization_unit_id' => $unit->id,
            'role' => 'admin',
        ]);
    }

    public function test_operator_cannot_assign_user_outside_formal_subtree(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $otherUnit = OrganizationUnit::factory()->create();
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $unit->id,
        ]);

        $this->expectException(ValidationException::class);

        app(CreateUnitAssignment::class)->handle($operator, [
            'user_id' => User::factory()->create()->id,
            'organization_unit_id' => $otherUnit->id,
            'role' => 'operator',
        ]);
    }
}
