<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthViewsRenderTest extends TestCase
{
    public function test_login_page_renders_required_firebase_and_recovery_hooks(): void
    {
        // Spec §3 contract: all DOM hooks consumed by firebase-auth.js must remain rendered.
        // Assert IDs/data attributes without coupling to incidental copy or class implementation.
        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('data-firebase-login-wrapper', $html, 'Root wrapper hook missing.');
        $this->assertStringContainsString('data-firebase-login', $html, 'Login section hook missing.');
        $this->assertStringContainsString('id="login-form"', $html, 'Login form ID missing.');
        $this->assertStringContainsString('id="input-email"', $html, 'Email input ID missing.');
        $this->assertStringContainsString('id="input-password"', $html, 'Password input ID missing.');
        $this->assertStringContainsString('id="login-submit-button"', $html, 'Login submit button ID missing.');
        $this->assertStringContainsString('id="google-login-button"', $html, 'Google login button ID missing.');
        $this->assertStringContainsString('id="login-status"', $html, 'Login status element ID missing.');

        // Recovery/linking flow hooks
        $this->assertStringContainsString('id="link-password-section"', $html, 'Link password section ID missing.');
        $this->assertStringContainsString('id="link-password-form"', $html, 'Link password form ID missing.');
        $this->assertStringContainsString('id="link-password"', $html, 'Link password input ID missing.');
        $this->assertStringContainsString('id="link-google-submit"', $html, 'Link Google submit button ID missing.');
        $this->assertStringContainsString('id="link-google-cancel"', $html, 'Link Google cancel button ID missing.');

        // Firebase config script
        $this->assertStringContainsString('window._FIREBASE_CONFIG', $html, 'Firebase config script missing.');
    }

    public function test_login_page_has_responsive_layout_contract(): void
    {
        // Behavior-level layout contract: one marked root, one H1, desktop/mobile layout markers,
        // md responsive visibility switch, no document tags inside component, no activation hooks.
        $html = $this->get('/admin/login')->assertOk()->getContent();

        // One marked root component
        $this->assertSame(
            1,
            substr_count($html, 'data-firebase-login-wrapper'),
            'Expected exactly one data-firebase-login-wrapper root.',
        );

        // One H1 for accessibility
        $this->assertSame(
            1,
            preg_match_all('/<h1[\s>]/i', $html),
            'Expected exactly one H1 heading.',
        );

        // Semantic layout markers identify the desktop identity panel and mobile wordmark header.
        $this->assertSame(1, substr_count($html, 'data-login-desktop-panel'), 'Expected one desktop identity panel marker.');
        $this->assertSame(1, substr_count($html, 'data-login-mobile-header'), 'Expected one mobile brand header marker.');

        // Semantic Tailwind visibility utilities express the md breakpoint switch in rendered markup.
        $this->assertMatchesRegularExpression(
            '/class="(?=[^"]*\bhidden\b)(?=[^"]*\bmd:block\b)[^"]*"/',
            $html,
            'Expected desktop-only content to use hidden and md:block visibility utilities.',
        );
        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bmd:hidden\b[^"]*"/',
            $html,
            'Expected mobile-only content to use md:hidden visibility utility.',
        );

        // No document tags inside component; the outer Filament layout owns the full document.
        $componentStart = strpos($html, 'data-firebase-login-wrapper');
        $this->assertNotFalse($componentStart, 'Login component wrapper must be rendered.');
        $componentContent = substr($html, $componentStart, strpos($html, '</html>') - $componentStart);
        $this->assertStringNotContainsString('<!doctype', strtolower($componentContent), 'Component must not inject a nested doctype.');
        $this->assertStringNotContainsString('<html', strtolower($componentContent), 'Component must not inject nested <html>.');


        // No activation onboarding hooks on login page
        $this->assertStringNotContainsString('id="input-name"', $html, 'Activation name input must not appear on login page.');
        $this->assertStringNotContainsString('id="input-password-confirm"', $html, 'Activation confirmation input must not appear on login page.');
        $this->assertStringNotContainsString('Aktifkan Akun', $html, 'Activation submit text must not appear on login page.');
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

        // Login component content remains rendered inside the document.
        $this->assertStringContainsString('data-firebase-login', $html);
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
