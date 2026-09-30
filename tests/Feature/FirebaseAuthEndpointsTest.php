<?php

namespace Tests\Feature;

use App\Contracts\FirebaseAuthentication;
use App\Models\OrganizationUnit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FirebaseAuthEndpointsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_activate_endpoint_onboards_pending_operator_activates_assignments_and_starts_session(): void
    {
        $unit = OrganizationUnit::factory()->create();
        // Initial activation: the invited row has no Firebase UID bound yet, so the
        // endpoint must fall back to the verified email while firebase_uid is null.
        $user = User::factory()->create([
            'name' => null,
            'email' => 'calon.operator@gmail.com',
            'firebase_uid' => null,
            'is_active' => false,
            'onboarded_at' => null,
        ]);
        $assignment = UnitAssignment::factory()->create([
            'user_id' => $user->id,
            'organization_unit_id' => $unit->id,
            'is_active' => false,
        ]);

        $this->fakeFirebaseToken('valid_activation_token', [
            'uid' => 'fb_uid_activation_test',
            'email' => 'calon.operator@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/admin/auth/activate', [
            'id_token' => 'valid_activation_token',
            'name' => 'Nama Operator Resmi',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'activated',
            'redirect' => url('/admin'),
        ]);

        $user->refresh();
        $this->assertSame('Nama Operator Resmi', $user->name);
        $this->assertSame('fb_uid_activation_test', $user->firebase_uid);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->onboarded_at);
        $this->assertTrue($user->isOnboarded());
        $this->assertTrue($assignment->refresh()->is_active);
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->id,
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
            'action' => 'operator.activated',
        ]);
    }

    public function test_activate_endpoint_rejects_unverified_email_token(): void
    {
        $user = User::factory()->create([
            'name' => null,
            'email' => 'belum.verifikasi@gmail.com',
            'firebase_uid' => 'fb_uid_unverified',
            'is_active' => false,
            'onboarded_at' => null,
        ]);

        $this->fakeFirebaseToken('unverified_token', [
            'uid' => 'fb_uid_unverified',
            'email' => 'belum.verifikasi@gmail.com',
            'email_verified' => false,
        ]);

        $response = $this->postJson('/admin/auth/activate', [
            'id_token' => 'unverified_token',
            'name' => 'Nama Operator Resmi',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Email belum diverifikasi oleh Firebase.']);
        $this->assertGuest();

        $user->refresh();
        $this->assertNull($user->onboarded_at);
        $this->assertNull($user->name);
        $this->assertFalse($user->is_active);
    }

    public function test_activate_endpoint_rejects_already_onboarded_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Operator Sudah Aktif',
            'email' => 'sudah.aktivasi@gmail.com',
            'firebase_uid' => 'fb_uid_already_onboarded',
            'is_active' => true,
            'onboarded_at' => now(),
        ]);

        $this->fakeFirebaseToken('already_onboarded_token', [
            'uid' => 'fb_uid_already_onboarded',
            'email' => 'sudah.aktivasi@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/admin/auth/activate', [
            'id_token' => 'already_onboarded_token',
            'name' => 'Nama Baru Tidak Terpakai',
        ]);

        // Re-activation of an already-onboarded account is a forbidden operation (403),
        // not a malformed request (400).
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Akun ini sudah pernah diaktivasi sebelumnya.']);
        $this->assertGuest();

        $this->assertSame('Operator Sudah Aktif', $user->refresh()->name);
    }

    public function test_activate_endpoint_rejects_unknown_identity(): void
    {
        $this->fakeFirebaseToken('unknown_identity_token', [
            'uid' => 'fb_uid_never_invited',
            'email' => 'tidak.dikenal@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/admin/auth/activate', [
            'id_token' => 'unknown_identity_token',
            'name' => 'Nama Operator Resmi',
        ]);

        // An identity that was never invited cannot be activated: forbidden (403).
        $response->assertStatus(403);
        $response->assertJson(['error' => 'Undangan aktivasi tidak ditemukan.']);
        $this->assertGuest();

        $this->assertDatabaseMissing('users', ['email' => 'tidak.dikenal@gmail.com']);
    }

    public function test_activate_endpoint_rejects_token_email_mismatch_for_bound_uid(): void
    {
        // The Firebase UID is bound to a local operator, but the verified token carries a
        // different email. The lookup must not treat them as the same identity.
        $user = User::factory()->create([
            'name' => null,
            'email' => 'pemilik.asli@gmail.com',
            'firebase_uid' => 'fb_uid_bound_owner',
            'is_active' => false,
            'onboarded_at' => null,
        ]);

        $this->fakeFirebaseToken('mismatched_email_token', [
            'uid' => 'fb_uid_bound_owner',
            'email' => 'penyerang.lain@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/admin/auth/activate', [
            'id_token' => 'mismatched_email_token',
            'name' => 'Nama Penyerang',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Undangan aktivasi tidak ditemukan.']);
        $this->assertGuest();

        $user->refresh();
        $this->assertNull($user->name);
        $this->assertNull($user->onboarded_at);
        $this->assertFalse($user->is_active);
    }

    public function test_activate_endpoint_does_not_bind_uid_to_unrelated_pending_email(): void
    {
        // A pending invite exists for another address; a token must not be able to claim it
        // by presenting a UID whose verified email differs from the invited email.
        $pending = User::factory()->create([
            'name' => null,
            'email' => 'diundang@gmail.com',
            'firebase_uid' => null,
            'is_active' => false,
            'onboarded_at' => null,
        ]);

        $this->fakeFirebaseToken('other_uid_token', [
            'uid' => 'fb_uid_other_person',
            'email' => 'orang.lain@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/admin/auth/activate', [
            'id_token' => 'other_uid_token',
            'name' => 'Nama Orang Lain',
        ]);

        $response->assertStatus(403);
        $this->assertGuest();

        $pending->refresh();
        $this->assertNull($pending->firebase_uid);
        $this->assertFalse($pending->is_active);
        $this->assertNull($pending->onboarded_at);
    }

    public function test_activate_endpoint_rejects_revoked_or_invalid_token_with_unauthorized(): void
    {
        $firebase = Mockery::mock(FirebaseAuthentication::class);
        $firebase->shouldReceive('verifyIdToken')
            ->once()
            ->with('revoked_token')
            ->andThrow(new RuntimeException('Token autentikasi tidak valid atau telah dicabut.'));
        $this->app->instance(FirebaseAuthentication::class, $firebase);

        $response = $this->postJson('/admin/auth/activate', [
            'id_token' => 'revoked_token',
            'name' => 'Nama Operator Resmi',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Token autentikasi tidak valid atau telah kedaluwarsa.']);
        $this->assertGuest();
    }

    public function test_session_endpoint_authenticates_onboarded_operator(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $user = User::factory()->create([
            'name' => 'Operator Terverifikasi',
            'email' => 'operator.aktif@gmail.com',
            'firebase_uid' => 'fb_uid_onboarded',
            'is_active' => true,
            'onboarded_at' => now(),
        ]);
        UnitAssignment::factory()->create([
            'user_id' => $user->id,
            'organization_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $this->fakeFirebaseToken('valid_session_token', [
            'uid' => 'fb_uid_onboarded',
            'email' => 'operator.aktif@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/admin/auth/session', [
            'id_token' => 'valid_session_token',
        ]);

        $response->assertOk();
        $response->assertJson([
            'status' => 'authenticated',
            'redirect' => url('/admin'),
        ]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_session_endpoint_rejects_unonboarded_pending_operator(): void
    {
        $user = User::factory()->create([
            'name' => null,
            'email' => 'belum.onboarding@gmail.com',
            'firebase_uid' => 'fb_uid_pending',
            'is_active' => false,
            'onboarded_at' => null,
        ]);

        $this->fakeFirebaseToken('token_for_pending_user', [
            'uid' => 'fb_uid_pending',
            'email' => 'belum.onboarding@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/admin/auth/session', [
            'id_token' => 'token_for_pending_user',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Akun belum menyelesaikan aktivasi onboarding.']);
        $this->assertGuest();
    }

    public function test_session_endpoint_rejects_inactive_operator(): void
    {
        $user = User::factory()->create([
            'name' => 'Operator Dinonaktifkan',
            'email' => 'operator.nonaktif@gmail.com',
            'firebase_uid' => 'fb_uid_inactive',
            'is_active' => false,
            'onboarded_at' => now(),
        ]);

        $this->fakeFirebaseToken('inactive_operator_token', [
            'uid' => 'fb_uid_inactive',
            'email' => 'operator.nonaktif@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/admin/auth/session', [
            'id_token' => 'inactive_operator_token',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Akun operator tidak ditemukan atau dinonaktifkan.']);
        $this->assertGuest();
    }

    public function test_session_endpoint_rejects_unknown_firebase_uid(): void
    {
        $this->fakeFirebaseToken('unknown_uid_token', [
            'uid' => 'fb_uid_does_not_exist',
            'email' => 'asing.tanpa.uid@gmail.com',
            'email_verified' => true,
        ]);

        $response = $this->postJson('/admin/auth/session', [
            'id_token' => 'unknown_uid_token',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Akun operator tidak ditemukan atau dinonaktifkan.']);
        $this->assertGuest();
    }

    public function test_session_endpoint_rejects_invalid_token_with_unauthorized(): void
    {
        $firebase = Mockery::mock(FirebaseAuthentication::class);
        $firebase->shouldReceive('verifyIdToken')
            ->once()
            ->with('expired_token')
            ->andThrow(new RuntimeException('Token autentikasi tidak valid atau telah dicabut.'));
        $this->app->instance(FirebaseAuthentication::class, $firebase);

        $response = $this->postJson('/admin/auth/session', [
            'id_token' => 'expired_token',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Token autentikasi tidak valid atau telah kedaluwarsa.']);
        $this->assertGuest();
    }

    /**
     * Bind a Firebase double that resolves the given token to the given claims.
     *
     * @param  array{uid: string, email: string, email_verified: bool}  $claims
     */
    private function fakeFirebaseToken(string $idToken, array $claims): void
    {
        $firebase = Mockery::mock(FirebaseAuthentication::class);
        $firebase->shouldReceive('verifyIdToken')
            ->once()
            ->with($idToken)
            ->andReturn($claims);

        $this->app->instance(FirebaseAuthentication::class, $firebase);
    }
}
