<?php

namespace Tests\Feature;

use App\Actions\CreateOperator;
use App\Contracts\FirebaseAuthentication;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\OrganizationUnitType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CreateOperatorActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_provinsi_operator_can_invite_operator_for_any_unit(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $ranting = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Ranting]);
        $actor = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $actor->id,
            'organization_unit_id' => $provinsi->id,
            'role' => 'operator',
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')
            ->once()
            ->with('calon.ranting@gmail.com')
            ->andReturn(['uid' => 'fb_uid_999']);
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $created = app(CreateOperator::class)->handle($actor, [
            'email' => 'calon.ranting@gmail.com',
            'organization_unit_id' => $ranting->id,
        ]);

        $this->assertSame('calon.ranting@gmail.com', $created->email);
        $this->assertSame('fb_uid_999', $created->firebase_uid);
        $this->assertFalse($created->is_active);
        $this->assertNull($created->onboarded_at);
        $this->assertNull($created->name);

        $this->assertDatabaseHas('unit_assignments', [
            'user_id' => $created->id,
            'organization_unit_id' => $ranting->id,
            'is_active' => false,
            'role' => 'operator',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'action' => 'operator.invited',
            'actor_id' => $actor->id,
            'subject_type' => $created->getMorphClass(),
            'subject_id' => $created->id,
        ]);
    }

    public function test_cabang_operator_can_invite_operator_for_formal_descendants_ranting_and_rayon(): void
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
        $rayon = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Rayon,
            'parent_id' => $ranting->id,
        ]);
        $actor = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $actor->id,
            'organization_unit_id' => $cabang->id,
            'role' => 'operator',
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')
            ->andReturnUsing(fn (string $email): array => [
                'uid' => 'fb_uid_'.md5($email),
            ]);
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $rantingOperator = app(CreateOperator::class)->handle($actor, [
            'email' => 'ranting.operator@gmail.com',
            'organization_unit_id' => $ranting->id,
        ]);

        $rayonOperator = app(CreateOperator::class)->handle($actor, [
            'email' => 'rayon.operator@gmail.com',
            'organization_unit_id' => $rayon->id,
        ]);

        $this->assertDatabaseHas('unit_assignments', [
            'user_id' => $rantingOperator->id,
            'organization_unit_id' => $ranting->id,
            'role' => 'operator',
        ]);
        $this->assertDatabaseHas('unit_assignments', [
            'user_id' => $rayonOperator->id,
            'organization_unit_id' => $rayon->id,
            'role' => 'operator',
        ]);
    }

    public function test_cabang_operator_cannot_invite_for_another_cabang_or_provinsi_or_ukm(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $cabang1 = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $provinsi->id,
        ]);
        $cabang2 = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Cabang,
            'parent_id' => $provinsi->id,
        ]);
        $ukm = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Ukm,
            'parent_id' => $provinsi->id,
            'supervising_branch_id' => $cabang1->id,
        ]);
        $actor = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $actor->id,
            'organization_unit_id' => $cabang1->id,
            'role' => 'operator',
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        foreach ([$cabang2, $provinsi, $ukm] as $forbiddenUnit) {
            try {
                app(CreateOperator::class)->handle($actor, [
                    'email' => 'operator.ilegal@gmail.com',
                    'organization_unit_id' => $forbiddenUnit->id,
                ]);

                $this->fail("Expected ValidationException for unit {$forbiddenUnit->id} ({$forbiddenUnit->type->value}).");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('organization_unit_id', $exception->errors());
            }
        }
    }

    #[DataProvider('subCabangOperatorTypes')]
    public function test_sub_cabang_operator_cannot_invite_any_operator(OrganizationUnitType $actorUnitType): void
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
        $rayon = OrganizationUnit::factory()->create([
            'type' => OrganizationUnitType::Rayon,
            'parent_id' => $ranting->id,
        ]);
        $actorUnit = $actorUnitType === OrganizationUnitType::Ranting ? $ranting : $rayon;
        $actor = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $actor->id,
            'organization_unit_id' => $actorUnit->id,
            'role' => 'operator',
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        try {
            app(CreateOperator::class)->handle($actor, [
                'email' => 'operator.bawah@gmail.com',
                'organization_unit_id' => $rayon->id,
            ]);

            $this->fail("Expected ValidationException for a {$actorUnitType->value} operator.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('organization_unit_id', $exception->errors());
        }

        $this->assertDatabaseMissing('users', ['email' => 'operator.bawah@gmail.com']);
    }

    /**
     * @return array<string, array{OrganizationUnitType}>
     */
    public static function subCabangOperatorTypes(): array
    {
        return [
            'ranting' => [OrganizationUnitType::Ranting],
            'rayon' => [OrganizationUnitType::Rayon],
        ];
    }

    public function test_rejects_non_gmail_addresses(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $ranting = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Ranting]);
        $actor = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $actor->id,
            'organization_unit_id' => $provinsi->id,
            'role' => 'operator',
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        foreach (['operator@yahoo.com', 'operator@psht.id'] as $email) {
            try {
                app(CreateOperator::class)->handle($actor, [
                    'email' => $email,
                    'organization_unit_id' => $ranting->id,
                ]);

                $this->fail("Expected ValidationException for email {$email}.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('email', $exception->errors());
                $this->assertStringContainsString('gmail', strtolower($exception->errors()['email'][0]));
            }
        }
    }

    public function test_rejects_duplicate_email(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $ranting = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Ranting]);
        $actor = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $actor->id,
            'organization_unit_id' => $provinsi->id,
            'role' => 'operator',
        ]);
        User::factory()->create(['email' => 'sudah.ada@gmail.com']);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')->never();
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $this->expectException(ValidationException::class);

        app(CreateOperator::class)->handle($actor, [
            'email' => 'sudah.ada@gmail.com',
            'organization_unit_id' => $ranting->id,
        ]);
    }

    public function test_persists_inactive_pending_record_before_calling_firebase(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $ranting = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Ranting]);
        $actor = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $actor->id,
            'organization_unit_id' => $provinsi->id,
            'role' => 'operator',
        ]);

        // Stage 1 must commit the pending local record *before* the Firebase network call so a
        // saga retry can reuse it instead of creating a duplicate account.
        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')
            ->once()
            ->with('saga.operator@gmail.com')
            ->andReturnUsing(function (string $email): array {
                $pending = User::query()->where('email', $email)->first();
                $this->assertNotNull($pending, 'Pending user must exist before the Firebase call.');
                $this->assertFalse($pending->is_active);
                $this->assertNull($pending->firebase_uid);
                $this->assertSame(1, $pending->unitAssignments()->count());
                $this->assertDatabaseHas('audit_events', ['action' => 'operator.invitation_requested', 'subject_id' => $pending->id]);

                return ['uid' => 'fb_uid_saga'];
            });
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        $created = app(CreateOperator::class)->handle($actor, [
            'email' => 'saga.operator@gmail.com',
            'organization_unit_id' => $ranting->id,
        ]);

        $this->assertSame('fb_uid_saga', $created->firebase_uid);
        $this->assertDatabaseHas('audit_events', ['action' => 'operator.invited', 'subject_id' => $created->id]);
    }

    public function test_keeps_inactive_pending_record_when_firebase_call_fails(): void
    {
        $provinsi = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Provinsi]);
        $ranting = OrganizationUnit::factory()->create(['type' => OrganizationUnitType::Ranting]);
        $actor = User::factory()->create();
        UnitAssignment::factory()->create([
            'user_id' => $actor->id,
            'organization_unit_id' => $provinsi->id,
            'role' => 'operator',
        ]);

        $firebaseMock = Mockery::mock(FirebaseAuthentication::class);
        $firebaseMock->shouldReceive('createUserWithInviteLink')
            ->once()
            ->with('gagal.firebase@gmail.com')
            ->andThrow(new RuntimeException('Firebase down'));
        $this->app->instance(FirebaseAuthentication::class, $firebaseMock);

        try {
            app(CreateOperator::class)->handle($actor, [
                'email' => 'gagal.firebase@gmail.com',
                'organization_unit_id' => $ranting->id,
            ]);

            $this->fail('Expected RuntimeException when the Firebase call fails.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Firebase down', $exception->getMessage());
        }

        // The saga keeps the pending record inactive (never rolled back) so it can be retried
        // without creating a duplicate Firebase identity.
        $pending = User::query()->where('email', 'gagal.firebase@gmail.com')->first();
        $this->assertNotNull($pending);
        $this->assertNull($pending->firebase_uid);
        $this->assertFalse($pending->is_active);
        $this->assertNull($pending->onboarded_at);

        $this->assertDatabaseHas('unit_assignments', [
            'user_id' => $pending->id,
            'organization_unit_id' => $ranting->id,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'action' => 'operator.invitation_requested',
            'subject_id' => $pending->id,
        ]);
        $this->assertDatabaseMissing('audit_events', [
            'action' => 'operator.invited',
            'subject_id' => $pending->id,
        ]);
    }
}
