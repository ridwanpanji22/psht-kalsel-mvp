<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Source-contract checks for the bundled Firebase client module.
 *
 * The project has no JavaScript test runner (no vitest/jest/mocha), so this test
 * asserts the behavioral contract at the source level: the
 * `auth/account-exists-with-different-credential` recovery path must LINK the pending
 * Google credential to the password-authenticated user via `linkWithCredential`, and
 * must NOT re-sign-in with `signInWithCredential`.
 *
 * NOTE: this is a static/source assertion only. It cannot execute the browser flow;
 * see the task report for the manual/browser proof.
 */
class FirebaseAuthClientSourceTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(resource_path('js/firebase-auth.js'));
    }

    public function test_account_exists_handler_links_pending_credential_to_password_user(): void
    {
        $source = $this->source();
        $handler = $this->accountExistsHandlerSource($source);

        // The recovery path must present the password prompt and fetch the pending credential.
        $this->assertStringContainsString('GoogleAuthProvider.credentialFromError(error)', $handler);

        // Password sign-in yields the target user, then the pending Google credential is LINKED.
        $this->assertStringContainsString('signInWithEmailAndPassword(auth, email, password)', $handler);
        $this->assertStringContainsString('linkWithCredential(credential.user, pendingCredential)', $handler);

        // The LINKED user's token is what completes the session exchange.
        $this->assertStringContainsString('linked.user.getIdToken(true)', $handler);
        $this->assertStringNotContainsString('signInWithCredential', $handler);
    }

    /**
     * Extract the body of the `handleAccountExists` function so assertions do not
     * accidentally match the unrelated normal-login code path.
     */
    private function accountExistsHandlerSource(string $source): string
    {
        $start = strpos($source, 'async function handleAccountExists');

        $this->assertNotFalse($start, 'handleAccountExists() must exist in firebase-auth.js');

        $end = strpos($source, 'function initActivate', $start);

        return substr($source, $start, $end === false ? null : $end - $start);
    }

    public function test_account_exists_handler_does_not_resign_in_with_pending_credential(): void
    {
        $handler = $this->accountExistsHandlerSource($this->source());

        // Regression guard: the email-already-bound recovery path must never call
        // `signInWithCredential`, which would re-attempt sign-in instead of linking.
        $this->assertStringNotContainsString('signInWithCredential', $handler);
    }
}
