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
