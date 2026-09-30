<?php

namespace Tests\Feature;

use App\Contracts\FirebaseAuthentication;
use App\Models\AuditEvent;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\OrganizationUnitType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MigrateOperatorToFirebaseCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_migrate_command_binds_uid_and_deactivates_legacy_operator(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $operator = User::factory()->create([
            'name' => 'Operator Lama',
            'email' => 'operator.lama@gmail.com',
            'firebase_uid' => null,
            'is_active' => true,
            'onboarded_at' => now(),
        ]);
        $assignment = UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $provinsi->id,
            'role' => 'operator',
            'is_active' => true,
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')
            ->once()
            ->with('operator.lama@gmail.com')
            ->andReturn(['uid' => 'fb_uid_migrated_456', 'sign_in_link' => 'https://migrate.link']);
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->artisan('operators:migrate-firebase', ['email' => 'operator.lama@gmail.com'])
            ->expectsOutputToContain('Undangan migrasi Firebase berhasil dikirimkan')
            ->assertSuccessful();

        $operator->refresh();

        // Identity preserved.
        $this->assertSame($operator->id, $operator->id);
        $this->assertSame('Operator Lama', $operator->name);
        $this->assertSame('operator.lama@gmail.com', $operator->email);

        // Firebase UID bound, activation reset.
        $this->assertSame('fb_uid_migrated_456', $operator->firebase_uid);
        $this->assertNull($operator->onboarded_at);

        $assignment->refresh();
        $this->assertSame($operator->id, $assignment->user_id);
        $this->assertSame($provinsi->id, $assignment->organization_unit_id);
        $this->assertSame('operator', $assignment->role);
        $this->assertFalse($assignment->is_active);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'operator.firebase_migration_invited',
            'actor_id' => null,
            'subject_type' => $operator->getMorphClass(),
            'subject_id' => $operator->id,
        ]);
    }

    public function test_migrate_command_rejects_unknown_email(): void
    {
        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->artisan('operators:migrate-firebase', ['email' => 'tidak.ada@gmail.com'])
            ->assertFailed();
    }

    public function test_migrate_command_rejects_user_with_existing_firebase_uid(): void
    {
        $user = User::factory()->create([
            'email' => 'sudah.migrasi@gmail.com',
            'firebase_uid' => 'fb_uid_existing',
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->artisan('operators:migrate-firebase', ['email' => 'sudah.migrasi@gmail.com'])
            ->assertFailed();

        $this->assertSame('fb_uid_existing', $user->refresh()->firebase_uid);
    }

    public function test_migrate_command_rejects_user_without_assignments(): void
    {
        User::factory()->create([
            'email' => 'tanpa.assignment@gmail.com',
            'firebase_uid' => null,
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->artisan('operators:migrate-firebase', ['email' => 'tanpa.assignment@gmail.com'])
            ->assertFailed();
    }

    public function test_migrate_command_leaves_user_and_assignments_unchanged_when_firebase_fails(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $operator = User::factory()->create([
            'name' => 'Operator Lama',
            'email' => 'gagal.migrasi@gmail.com',
            'firebase_uid' => null,
            'is_active' => true,
            'onboarded_at' => now(),
        ]);
        $assignment = UnitAssignment::factory()->create([
            'user_id' => $operator->id,
            'organization_unit_id' => $provinsi->id,
            'role' => 'operator',
            'is_active' => true,
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')
            ->once()
            ->with('gagal.migrasi@gmail.com')
            ->andThrow(new RuntimeException('Firebase down'));
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'migrasi')
                && ($context['email'] ?? null) === 'gagal.migrasi@gmail.com'
                && ($context['error'] ?? null) === 'Firebase down');

        $this->artisan('operators:migrate-firebase', ['email' => 'gagal.migrasi@gmail.com'])
            ->assertFailed();

        $operator->refresh();
        $this->assertNull($operator->firebase_uid);
        $this->assertNotNull($operator->onboarded_at);
        $this->assertTrue($operator->is_active);
        $this->assertSame('Operator Lama', $operator->name);

        $assignment->refresh();
        $this->assertTrue($assignment->is_active);

        $this->assertSame(0, AuditEvent::query()->where('action', 'operator.firebase_migration_invited')->count());
    }
}
