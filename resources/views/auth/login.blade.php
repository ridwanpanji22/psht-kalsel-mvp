@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $firebaseConfig = config('services.firebase.client');
@endphp

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Masuk Operator — PSHT Kalimantan Selatan</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<div class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <nav class="mx-auto flex max-w-2xl items-center justify-between gap-4 px-4 py-4" aria-label="Navigasi utama">
            <a href="{{ route('public.home') }}" class="font-semibold text-emerald-900">PSHT Kalimantan Selatan</a>
            <a href="{{ route('public.home') }}" class="text-sm underline-offset-4 hover:underline">Portal publik</a>
        </nav>
    </header>

    <main class="mx-auto max-w-md px-4 py-10">
        <section data-firebase-login class="rounded-xl border border-stone-200 bg-white p-6 sm:p-8" aria-labelledby="login-heading">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">Panel operator</p>
            <h1 id="login-heading" class="mt-2 text-3xl font-bold tracking-tight">Masuk</h1>
            <p class="mt-2 text-sm text-stone-600">Gunakan email dan kata sandi operator Anda, atau masuk dengan akun Google.</p>

            <p id="login-status" class="mt-4 hidden rounded-md border p-3 text-sm text-stone-700" role="status" aria-live="polite"></p>

            <form id="login-form" class="mt-6 space-y-5" novalidate>
                <div>
                    <label for="input-email" class="block text-sm font-medium text-stone-700">Email</label>
                    <input id="input-email" name="email" type="email" required autocomplete="email" autofocus
                        class="mt-1 block w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
                </div>

                <div>
                    <label for="input-password" class="block text-sm font-medium text-stone-700">Kata Sandi</label>
                    <input id="input-password" name="password" type="password" required autocomplete="current-password"
                        class="mt-1 block w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
                </div>

                <button type="submit" id="login-submit-button"
                    class="inline-flex w-full items-center justify-center rounded-md bg-emerald-800 px-5 py-3 font-medium text-white hover:bg-emerald-900 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-800 disabled:opacity-60">
                    Masuk
                </button>
            </form>

            <div class="mt-6 flex items-center gap-3 text-xs uppercase tracking-wide text-stone-400" aria-hidden="true">
                <span class="h-px flex-1 bg-stone-200"></span>
                <span>atau</span>
                <span class="h-px flex-1 bg-stone-200"></span>
            </div>

            <button type="button" id="google-login-button"
                class="mt-6 inline-flex w-full items-center justify-center gap-3 rounded-md border border-stone-300 bg-white px-5 py-3 font-medium hover:bg-stone-100 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-800 disabled:opacity-60">
                <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1Z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z"/>
                    <path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.06H2.18a11 11 0 0 0 0 9.88l3.66-2.84Z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1A11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.31 9.14 5.38 12 5.38Z"/>
                </svg>
                <span>Masuk dengan Google</span>
            </button>

            {!! FilamentView::renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) !!}
        </section>
    </main>

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
</div>
</html>
