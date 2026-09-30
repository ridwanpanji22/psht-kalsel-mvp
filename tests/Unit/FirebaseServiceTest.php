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
    public function test_creates_user_and_generates_sign_in_link(): void
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

        $firebaseAuth->shouldReceive('getSignInWithEmailLink')
            ->once()
            ->with('operator@gmail.com', Mockery::type('array'))
            ->andReturn('https://pengprov-psht-kalsel.firebaseapp.com/__/auth/action?apiKey=test');

        $service = new FirebaseService($firebaseAuth);
        $result = $service->createUserWithInviteLink('operator@gmail.com');

        $this->assertSame('firebase_user_abc_123', $result['uid']);
        $this->assertStringContainsString('https://', $result['sign_in_link']);
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
