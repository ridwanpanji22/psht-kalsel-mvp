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

    public function test_login_page_renders_as_a_single_valid_document_without_nested_layout_tags(): void
    {
        // Regression for the Livewire "missing closing tag" warning: the custom login view
        // must be a valid Livewire component root, not a full document. Filament's layout
        // already renders <!doctype html>, <html>, <head> and <body>, so a component view that
        // injects its own document tags produces nested duplicates (and a stray </html> that
        // warns about missing closing tags). Assert exactly one document and one component root.
        $html = $this->get('/admin/login')->assertOk()->getContent();
        $lower = strtolower($html);

        $this->assertSame(1, substr_count($lower, '<!doctype html'), 'Expected exactly one <!doctype html>.');
        $this->assertSame(1, substr_count($lower, '<html'), 'Expected exactly one <html>.');
        $this->assertSame(1, substr_count($lower, '</html>'), 'Expected exactly one </html>.');
        // Use an explicit boundary so <header> is not mistaken for <head>.
        $this->assertSame(1, preg_match_all('/<head[\s>]/i', $html), 'Expected exactly one <head>.');
        $this->assertSame(1, substr_count($lower, '</head>'), 'Expected exactly one </head>.');
        $this->assertSame(1, substr_count($lower, '<body'), 'Expected exactly one <body>.');
        $this->assertSame(1, substr_count($lower, '</body>'), 'Expected exactly one </body>.');

        // The login component must render as a single Livewire component root (the Login page).
        // Filament's layout may additionally render its own components (e.g. notifications),
        // but the custom view must not add a second document or nested <html>/<head>.
        $this->assertSame(
            1,
            preg_match_all('/wire:name="App\\\\Filament\\\\Auth\\\\Pages\\\\Login"/', $html),
            'Expected a single Login Livewire component root.',
        );

        // The document must not contain any content after </html> (a stray component root).
        $closingHtml = strpos($lower, '</html>');
        $this->assertNotFalse($closingHtml, 'Document must be closed.');
        $this->assertSame('', trim(substr($html, $closingHtml + strlen('</html>'))), 'No content may follow </html>.');

        // The component root must not inject a nested document of its own.
        $this->assertSame(1, substr_count(strtolower($html), '<!doctype'), 'Expected no nested doctype.');

        // The component content lives inside the Filament layout body, not after </html>.
        $customContent = strpos($html, 'data-firebase-login');
        $this->assertNotFalse($customContent, 'Login component content must be rendered.');
        $this->assertLessThan($closingHtml, $customContent, 'Login component must render inside the document, before </html>.');

        // Custom view must not re-declare the document title; Filament's layout owns <head>.
        $this->assertStringNotContainsString('<title>Masuk Operator', $html, 'Component view must not inject its own <title>.');

        // Login markers still present after the refactor.
        $this->assertStringContainsString('data-firebase-login', $html);
        $this->assertStringContainsString('Masuk dengan Google', $html);
        $this->assertStringContainsString('build/assets/app-', $html);
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
