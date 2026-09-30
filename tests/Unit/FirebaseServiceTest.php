<?php

namespace Tests\Unit;

use App\Services\FirebaseService;
use Kreait\Firebase\Auth\UserMetaData;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Contract\Auth as FirebaseAuthContract;
use Lcobucci\JWT\Token\DataSet;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Token\Signature;
use Mockery;
use Tests\TestCase;

class FirebaseServiceTest extends TestCase
{
    public function test_creates_user_and_sends_sign_in_email_link_in_indonesian(): void
    {
        $firebaseAuth = Mockery::mock(FirebaseAuthContract::class);
        $userRecord = new UserRecord(
            uid: 'firebase_user_abc_123',
            email: 'operator@gmail.com',
            emailVerified: false,
            displayName: null,
            phoneNumber: null,
            photoUrl: null,
            disabled: false,
            metadata: new UserMetaData(new \DateTimeImmutable('2026-10-01 00:00:00'), null, null, null),
            providerData: [],
            mfaInfo: null,
            passwordHash: null,
            passwordSalt: null,
            customClaims: [],
            tenantId: null,
            tokensValidAfterTime: null,
        );

        $firebaseAuth->shouldReceive('createUser')
            ->once()
            ->with(Mockery::on(fn ($arg) => is_array($arg)
                && $arg['email'] === 'operator@gmail.com'
                && $arg['emailVerified'] === false
                && $arg['disabled'] === false))
            ->andReturn($userRecord);

        // The invite must be SENT by the Admin SDK with the Indonesian locale, never
        // merely generated locally (which would leave the email unsent).
        $firebaseAuth->shouldReceive('sendSignInWithEmailLink')
            ->once()
            ->with('operator@gmail.com', Mockery::type('array'), 'id')
            ->andReturnNull();

        $firebaseAuth->shouldNotReceive('getSignInWithEmailLink');

        $service = new FirebaseService($firebaseAuth);
        $result = $service->createUserWithInviteLink('operator@gmail.com');

        $this->assertSame(['uid' => 'firebase_user_abc_123'], $result);
    }

    public function test_throws_clear_runtime_exception_when_firebase_credentials_missing(): void
    {
        // A service constructed without an injected client (the AppServiceProvider
        // fallback when credentials are absent) must fail with an actionable message
        // instead of attempting container resolution.
        $service = new FirebaseService;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Kredensial Firebase belum dikonfigurasi di FIREBASE_CREDENTIALS.');

        $service->createUserWithInviteLink('operator@gmail.com');
    }

    public function test_verifies_id_token_successfully(): void
    {
        $firebaseAuth = Mockery::mock(FirebaseAuthContract::class);
        $token = new Plain(
            new DataSet([], 'e30'),
            new DataSet([
                'sub' => 'firebase_user_abc_123',
                'email' => 'operator@gmail.com',
                'email_verified' => true,
            ], 'e30'),
            new Signature('hash', 'signature'),
        );

        $firebaseAuth->shouldReceive('verifyIdToken')
            ->once()
            ->with('valid_id_token_sample', true)
            ->andReturn($token);

        $service = new FirebaseService($firebaseAuth);
        $result = $service->verifyIdToken('valid_id_token_sample');

        $this->assertSame('firebase_user_abc_123', $result['uid']);
        $this->assertSame('operator@gmail.com', $result['email']);
        $this->assertTrue($result['email_verified']);
    }

    public function test_throws_runtime_exception_when_firebase_create_user_fails(): void
    {
        $firebaseAuth = Mockery::mock(FirebaseAuthContract::class);
        $firebaseAuth->shouldReceive('createUser')
            ->andThrow(new \RuntimeException('Firebase error'));

        $service = new FirebaseService($firebaseAuth);
        $this->expectException(\RuntimeException::class);
        $service->createUserWithInviteLink('operator@gmail.com');
    }

    public function test_throws_runtime_exception_when_verify_token_fails(): void
    {
        $firebaseAuth = Mockery::mock(FirebaseAuthContract::class);
        $firebaseAuth->shouldReceive('verifyIdToken')
            ->andThrow(new \RuntimeException('Bad token'));

        $service = new FirebaseService($firebaseAuth);
        $this->expectException(\RuntimeException::class);
        $service->verifyIdToken('invalid_token');
    }
}
