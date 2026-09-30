<?php

namespace App\Contracts;

interface FirebaseAuthentication
{
    /**
     * Create the Firebase identity and trigger the invite email-link delivery.
     *
     * The invite link is generated and sent by Firebase directly to the target
     * address; it is deliberately never returned to the caller.
     *
     * @return array{uid: string}
     */
    public function createUserWithInviteLink(string $email): array;

    /**
     * @return array{uid: string, email: string, email_verified: bool}
     */
    public function verifyIdToken(string $idToken): array;
}
