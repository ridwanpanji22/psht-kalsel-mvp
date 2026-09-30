<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthViewsRenderTest extends TestCase
{
    public function test_login_page_renders_with_google_and_firebase_hooks(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Masuk dengan Google')
            ->assertSee('google-login-button')
            ->assertSee('data-firebase-login')
            ->assertSee('window._FIREBASE_CONFIG', escape: false)
            ->assertSee('build/assets/app-', escape: false);
    }

    public function test_login_page_renders_in_page_password_linking_markers(): void
    {
        // The account-exists recovery flow must expose an in-page password form instead of
        // a blocking window.prompt. These DOM markers are the contract the client JS binds
        // to, so assert them by rendered id.
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('link-password-section')
            ->assertSee('link-password-form')
            ->assertSee('link-password')
            ->assertSee('link-google-submit')
            ->assertSee('link-google-cancel')
            ->assertSee('Tautkan akun Google')
            ->assertSee('Tautkan &amp; Masuk', escape: false)
            ->assertSee('Batal');
    }

    public function test_activate_page_renders_onboarding_form_and_script_hooks(): void
    {
        $this->get('/admin/activate')
            ->assertOk()
            ->assertSee('Aktivasi Akun Operator')
            ->assertSee('data-firebase-activate')
            ->assertSee('input-name')
            ->assertSee('input-password')
            ->assertSee('input-password-confirm')
            ->assertSee('Aktifkan Akun')
            ->assertSee('window._FIREBASE_CONFIG', escape: false)
            ->assertSee('window._ACTIVATION_ENDPOINT', escape: false);
    }
}
