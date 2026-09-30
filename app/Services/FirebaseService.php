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

            // Ask Firebase to generate AND email the sign-in link to the invitee in
            // Indonesian. Unlike getSignInWithEmailLink(), this performs the actual
            // delivery, so the invite is genuinely sent rather than silently discarded.
            $auth->sendSignInWithEmailLink($email, $actionCodeSettings, 'id');

            return [
                'uid' => $userRecord->uid,
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

        // No client was injected because AppServiceProvider could not build one from
        // config('services.firebase.credentials'). Surface an actionable, localized
        // error instead of falling back to container resolution, which would fail with
        // an opaque binding exception when the credential file is absent.
        throw new RuntimeException('Kredensial Firebase belum dikonfigurasi di FIREBASE_CREDENTIALS.');
    }
}
