<?php

namespace App\Services;

use App\Contracts\FirebaseAuthentication;
use Illuminate\Support\Facades\URL;
use Kreait\Firebase\Contract\Auth as FirebaseAuthContract;
use Kreait\Firebase\Exception\AuthException;
use Kreait\Firebase\Exception\FirebaseException;
use RuntimeException;

class FirebaseService implements FirebaseAuthentication
{
    public function __construct(
        protected ?FirebaseAuthContract $auth = null
    ) {}

    public function createUserWithInviteLink(string $email): array
    {
        $auth = $this->getAuth();

        try {
            $userRecord = $auth->createUser([
                'email' => $email,
                'emailVerified' => false,
                'disabled' => false,
            ]);

            $actionCodeSettings = [
                'url' => URL::to('/admin/activate'),
                'handleCodeInApp' => true,
            ];

            $signInLink = $auth->getSignInWithEmailLink($email, $actionCodeSettings);

            return [
                'uid' => $userRecord->uid,
                'sign_in_link' => $signInLink,
            ];
        } catch (AuthException|FirebaseException $e) {
            throw new RuntimeException('Gagal memproses akun Firebase: '.$e->getMessage(), 0, $e);
        }
    }

    public function verifyIdToken(string $idToken): array
    {
        $auth = $this->getAuth();

        try {
            $verifiedToken = $auth->verifyIdToken($idToken, true);
            $claims = $verifiedToken->claims();

            return [
                'uid' => (string) $claims->get('sub'),
                'email' => (string) $claims->get('email'),
                'email_verified' => (bool) $claims->get('email_verified'),
            ];
        } catch (AuthException|FirebaseException $e) {
            throw new RuntimeException('Token autentikasi tidak valid atau telah dicabut.', 0, $e);
        }
    }

    protected function getAuth(): FirebaseAuthContract
    {
        if ($this->auth !== null) {
            return $this->auth;
        }

        // Resolve from container; must be bound in AppServiceProvider.
        return app(FirebaseAuthContract::class);
    }
}
