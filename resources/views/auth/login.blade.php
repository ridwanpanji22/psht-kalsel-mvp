@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $firebaseConfig = config('services.firebase.client');
@endphp

{{--
    This view is the body of a Livewire component rendered inside Filament's simple layout.
    It must expose exactly one root element (the component root) and must NOT declare
    <!doctype>, <html>, <head>, <body> or a closing </html>: Filament's
    `filament-panels::components.layout.base` already renders the full document, <head>,
    assets and <body>. Injecting document tags here nests a second document and triggers
    Livewire's "missing closing tag" warning.
--}}
<div data-firebase-login-wrapper class="login-shell">
    <div class="login-shell__layout">
        <aside data-login-desktop-panel class="login-shell__identity hidden md:block" aria-label="Identitas PSHT Kalimantan Selatan">
            <a href="{{ route('public.home') }}" class="login-shell__wordmark">PSHT <span>Kalimantan Selatan</span></a>
            <div class="login-shell__identity-copy">
                <p class="login-shell__eyebrow">Portal operator</p>
                <h2>Jaga gerak organisasi tetap terarah.</h2>
                <p>Kelola layanan internal PSHT Kalimantan Selatan dengan akses operator yang aman dan terukur.</p>
            </div>
            <a href="{{ route('public.home') }}" class="login-shell__public-link">Kembali ke portal publik</a>
        </aside>

        <div class="login-shell__form-column">
            <header data-login-mobile-header class="login-shell__mobile-header md:hidden">
                <a href="{{ route('public.home') }}" class="login-shell__wordmark">PSHT <span>Kalimantan Selatan</span></a>
                <p>Portal operator</p>
            </header>

            <section data-firebase-login class="login-shell__form-card" aria-labelledby="login-heading">
                <div class="login-shell__heading">
                    <p class="login-shell__eyebrow">Akses operator</p>
                    <h1 id="login-heading">Masuk ke panel operator</h1>
                    <p>Gunakan email dan kata sandi operator Anda, atau masuk dengan akun Google.</p>
                </div>

                <p id="login-status" class="login-shell__status hidden" role="status" aria-live="polite"></p>

                <form id="login-form" class="login-shell__form" novalidate>
                    <div class="login-shell__field">
                        <label for="input-email">Email</label>
                        <input id="input-email" name="email" type="email" required autocomplete="email" autofocus placeholder="nama@organisasi.id">
                    </div>

                    <div class="login-shell__field">
                        <label for="input-password">Kata Sandi</label>
                        <input id="input-password" name="password" type="password" required autocomplete="current-password" placeholder="Masukkan kata sandi">
                    </div>

                    <button type="submit" id="login-submit-button" class="login-shell__button login-shell__button--primary">
                        Masuk
                    </button>
                </form>

                <div class="login-shell__divider" aria-hidden="true">
                    <span></span>
                    <span>atau</span>
                    <span></span>
                </div>

                <button type="button" id="google-login-button" class="login-shell__button login-shell__button--google">
                    <svg class="login-shell__google-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1Z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z"/>
                        <path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84Z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38Z"/>
                    </svg>
                    <span>Masuk dengan Google</span>
                </button>

                <section id="link-password-section" class="login-shell__recovery hidden" aria-labelledby="link-password-heading">
                    <h2 id="link-password-heading">Tautkan akun Google</h2>
                    <p>Email ini sudah terdaftar dengan kata sandi. Masukkan kata sandi akun operator Anda untuk menautkan akun Google ini.</p>

                    <form id="link-password-form" class="login-shell__form" novalidate>
                        <div class="login-shell__field">
                            <label for="link-password">Kata Sandi Akun Operator</label>
                            <input id="link-password" name="link_password" type="password" required autocomplete="current-password" placeholder="Masukkan kata sandi">
                        </div>

                        <div class="login-shell__recovery-actions">
                            <button type="submit" id="link-google-submit" class="login-shell__button login-shell__button--primary">Tautkan &amp; Masuk</button>
                            <button type="button" id="link-google-cancel" class="login-shell__button login-shell__button--cancel">Batal</button>
                        </div>
                    </form>
                </section>

                {!! FilamentView::renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) !!}

                <script>
                    window._FIREBASE_CONFIG = {
                        apiKey: @json($firebaseConfig['api_key'] ?? null),
                        authDomain: @json($firebaseConfig['auth_domain'] ?? null),
                        projectId: @json($firebaseConfig['project_id'] ?? null),
                        storageBucket: @json($firebaseConfig['storage_bucket'] ?? null),
                        messagingSenderId: @json($firebaseConfig['messaging_sender_id'] ?? null),
                        appId: @json($firebaseConfig['app_id'] ?? null),
                    };
                </script>

                @vite(['resources/css/app.css', 'resources/js/app.js'])
            </section>
        </div>
    </div>
</div>
