<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Aktivasi akun operator PSHT Kalimantan Selatan.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Aktivasi Akun Operator — PSHT Kalimantan Selatan</title>
    <style>
        *, ::before, ::after { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        .hidden { display: none !important; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <nav class="mx-auto flex max-w-2xl items-center justify-between gap-4 px-4 py-4" aria-label="Navigasi utama">
            <a href="{{ route('public.home') }}" class="font-semibold text-emerald-900">PSHT Kalimantan Selatan</a>
            <a href="{{ route('filament.admin.auth.login') }}" class="text-sm underline-offset-4 hover:underline">Masuk</a>
        </nav>
    </header>

    <main class="mx-auto max-w-2xl px-4 py-10">
        <section data-firebase-activate aria-labelledby="activation-heading" class="rounded-xl border border-stone-200 bg-white p-6 sm:p-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-800">Onboarding operator</p>
            <h1 id="activation-heading" class="mt-2 text-3xl font-bold tracking-tight">Aktivasi Akun Operator</h1>

            <p id="status-message" class="mt-4 rounded-md border border-stone-200 bg-stone-50 p-3 text-sm text-stone-700" role="status" aria-live="polite">
                Harap tunggu sebentar...
            </p>

            <form id="activation-form" class="mt-6 hidden space-y-5" novalidate>
                <div>
                    <label for="input-name" class="block text-sm font-medium text-stone-700">Nama Lengkap</label>
                    <input id="input-name" name="name" type="text" required autocomplete="name"
                        class="mt-1 block w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
                </div>

                <div>
                    <label for="input-password" class="block text-sm font-medium text-stone-700">Kata Sandi Baru</label>
                    <input id="input-password" name="password" type="password" required minlength="8" autocomplete="new-password"
                        class="mt-1 block w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
                    <p class="mt-1 text-xs text-stone-500">Minimal 8 karakter.</p>
                </div>

                <div>
                    <label for="input-password-confirm" class="block text-sm font-medium text-stone-700">Konfirmasi Kata Sandi</label>
                    <input id="input-password-confirm" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"
                        class="mt-1 block w-full rounded-md border border-stone-300 px-3 py-2 shadow-sm focus:border-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-700">
                </div>

                <button type="submit" id="submit-button"
                    class="inline-flex items-center rounded-md bg-emerald-800 px-5 py-3 font-medium text-white hover:bg-emerald-900 focus:outline-2 focus:outline-offset-2 focus:outline-emerald-800 disabled:opacity-60">
                    Aktifkan Akun
                </button>
            </form>
        </section>
    </main>

    <script>
        window._FIREBASE_CONFIG = {
            apiKey: @json(config('services.firebase.client.api_key')),
            authDomain: @json(config('services.firebase.client.auth_domain')),
            projectId: @json(config('services.firebase.client.project_id')),
            storageBucket: @json(config('services.firebase.client.storage_bucket')),
            messagingSenderId: @json(config('services.firebase.client.messaging_sender_id')),
            appId: @json(config('services.firebase.client.app_id')),
        };
        window._ACTIVATION_ENDPOINT = @json(route('auth.firebase.activate'));
    </script>
</body>
</html>
