<?php

namespace Tests\Feature;

use App\Contracts\FirebaseAuthentication;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\OrganizationUnitType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class FilamentOperatorProvisioningTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_provinsi_operator_can_submit_invitation_via_filament_panel(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $provinsi->id,
            'role' => 'operator',
        ]);
        $this->actingAs($operator);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')
            ->once()
            ->with('operator.baru@gmail.com')
            ->andReturn(['uid' => 'fb_uid_panel_123']);
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'email' => 'operator.baru@gmail.com',
                'organization_unit_id' => $provinsi->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'operator.baru@gmail.com',
            'firebase_uid' => 'fb_uid_panel_123',
            'is_active' => false,
        ]);
    }

    public function test_cabang_operator_can_submit_invitation_for_ranting_via_filament_panel(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $cabang = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $provinsi->id,
        ]);
        $ranting = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Ranting,
            'parent_id' => $cabang->id,
        ]);
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $cabang->id,
            'role' => 'operator',
        ]);
        $this->actingAs($operator);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')
            ->once()
            ->with('ranting.baru@gmail.com')
            ->andReturn(['uid' => 'fb_uid_ranting_456']);
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'email' => 'ranting.baru@gmail.com',
                'organization_unit_id' => $ranting->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'ranting.baru@gmail.com',
            'firebase_uid' => 'fb_uid_ranting_456',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('unit_assignments', [
            'organization_unit_id' => $ranting->id,
            'role' => 'operator',
        ]);
    }

    public function test_user_resource_table_lists_only_operators_in_visible_scope(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $cabang = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $provinsi->id,
        ]);
        $ranting = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Ranting,
            'parent_id' => $cabang->id,
        ]);
        $outside = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Ranting]);

        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $cabang->id,
            'role' => 'operator',
        ]);

        $visibleOperator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $visibleOperator->id,
            'organization_unit_id' => $ranting->id,
            'role' => 'operator',
        ]);

        $outsideOperator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $outsideOperator->id,
            'organization_unit_id' => $outside->id,
            'role' => 'operator',
        ]);

        $this->actingAs($operator);

        $listed = UserResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($listed->contains($visibleOperator->id));
        $this->assertFalse($listed->contains($outsideOperator->id));
    }

    public function test_ranting_operator_cannot_access_create_operator_page(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $cabang = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $provinsi->id,
        ]);
        $ranting = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Ranting,
            'parent_id' => $cabang->id,
        ]);
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $ranting->id,
            'role' => 'operator',
        ]);
        $this->actingAs($operator);

        $this->assertFalse(UserResource::canCreate());

        Livewire::test(CreateUser::class)->assertForbidden();
    }

    public function test_listing_page_renders_for_provinsi_operator(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $operator = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $provinsi->id,
            'role' => 'operator',
        ]);
        $this->actingAs($operator);

        $this->assertTrue(UserResource::canCreate());

        Livewire::test(ListUsers::class)
            ->assertOk()
            ->assertSee('Nama Lengkap')
            ->assertSee('Email Gmail')
            ->assertCanSeeTableRecords([$operator]);
    }
}
