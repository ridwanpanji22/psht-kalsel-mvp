<?php

namespace Tests\Feature;

use App\Contracts\FirebaseAuthentication;
use App\Models\AuditEvent;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\OrganizationUnitType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class BootstrapProvinceOperatorCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_bootstrap_command_creates_pending_province_operator(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')
            ->once()
            ->with('admin.provinsi@gmail.com')
            ->andReturn(['uid' => 'fb_uid_bootstrap_123', 'sign_in_link' => 'https://bootstrap.link']);
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->artisan('operators:bootstrap-province', ['email' => 'admin.provinsi@gmail.com'])
            ->expectsOutputToContain('Undangan aktivasi berhasil dikirimkan')
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin.provinsi@gmail.com')->firstOrFail();

        $this->assertNull($user->name);
        $this->assertSame('fb_uid_bootstrap_123', $user->firebase_uid);
        $this->assertFalse($user->is_active);
        $this->assertNull($user->onboarded_at);

        $this->assertDatabaseHas('users', [
            'email' => 'admin.provinsi@gmail.com',
            'firebase_uid' => 'fb_uid_bootstrap_123',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('unit_assignments', [
            'user_id' => $user->id,
            'organization_unit_id' => $provinsi->id,
            'role' => 'operator',
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'operator.bootstrapped',
            'actor_id' => null,
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
        ]);
    }

    public function test_bootstrap_command_fails_if_active_province_operator_already_exists(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $existing = User::factory()->create(['is_active' => true]);
        UnitAssignment::factory()->create([
            'user_id' => $existing->id,
            'organization_unit_id' => $provinsi->id,
            'is_active' => true,
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->artisan('operators:bootstrap-province', ['email' => 'admin.kedua@gmail.com'])
            ->expectsOutputToContain('Operator Provinsi aktif sudah tersedia')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin.kedua@gmail.com']);
    }

    public function test_bootstrap_command_rejects_non_gmail_addresses(): void
    {
        OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->artisan('operators:bootstrap-province', ['email' => 'admin@yahoo.com'])
            ->expectsOutputToContain('Gmail')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin@yahoo.com']);
    }

    public function test_bootstrap_command_rejects_when_no_active_province_unit_exists(): void
    {
        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->artisan('operators:bootstrap-province', ['email' => 'admin.provinsi@gmail.com'])
            ->expectsOutputToContain('Provinsi')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin.provinsi@gmail.com']);
    }

    public function test_bootstrap_command_rejects_when_multiple_active_province_units_exist(): void
    {
        OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->artisan('operators:bootstrap-province', ['email' => 'admin.provinsi@gmail.com'])
            ->expectsOutputToContain('Provinsi')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin.provinsi@gmail.com']);
    }

    public function test_bootstrap_command_rolls_back_when_firebase_fails(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')
            ->once()
            ->with('gagal.bootstrap@gmail.com')
            ->andThrow(new RuntimeException('Firebase down'));
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->artisan('operators:bootstrap-province', ['email' => 'gagal.bootstrap@gmail.com'])
            ->expectsOutputToContain('Gagal mengirim undangan Firebase')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'gagal.bootstrap@gmail.com']);
        $this->assertSame(0, UnitAssignment::query()->where('organization_unit_id', $provinsi->id)->count());
        $this->assertSame(0, AuditEvent::query()->where('action', 'operator.bootstrapped')->count());
    }
}
