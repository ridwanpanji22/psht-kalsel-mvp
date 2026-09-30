<?php

namespace App\Contracts;

interface FirebaseAuthentication
{
    /**
     * @return array{uid: string, sign_in_link: string}
     */
    public function createUserWithInviteLink(string $email): array;

    /**
     * @return array{uid: string, email: string, email_verified: bool}
     */
    public function verifyIdToken(string $idToken): array;
}
