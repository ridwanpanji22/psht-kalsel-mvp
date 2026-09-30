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
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
    <header class="border-b border-stone-200 bg-white">
        <nav class="mx-auto flex max-w-2xl items-center justify-between gap-4 px-4 py-4" aria-label="Navigasi utama">
            <a href="{{ route('public.home') }}" class="font-semibold text-emerald-900">PSHT Kalimantan Selatan</a>
            <a href="{{ route('filament.admin.auth.login') }}" class="text-sm underline-offset-4 hover:underline">Masuk</a>
        </nav>
    </header>

    <main class="mx-auto max-w-2xl px-4 py-10">
        <section aria-labelledby="activation-heading" class="rounded-xl border border-stone-200 bg-white p-6 sm:p-8">
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
            apiKey: @json(env('FIREBASE_API_KEY')),
            authDomain: @json(env('FIREBASE_AUTH_DOMAIN')),
            projectId: @json(env('FIREBASE_PROJECT_ID')),
            storageBucket: @json(env('FIREBASE_STORAGE_BUCKET')),
            messagingSenderId: @json(env('FIREBASE_MESSAGING_SENDER_ID')),
            appId: @json(env('FIREBASE_APP_ID')),
        };
        window._ACTIVATION_ENDPOINT = @json(route('auth.firebase.activate'));
    </script>
    <script type="module">
        const statusElement = document.getElementById('status-message');
        const formElement = document.getElementById('activation-form');

        function setMessage(text, isError = false) {
            if (!statusElement) {
                return;
            }

            statusElement.textContent = text;
            statusElement.classList.toggle('border-red-300', isError);
            statusElement.classList.toggle('bg-red-50', isError);
            statusElement.classList.toggle('text-red-700', isError);
        }

        (async () => {
            try {
                const config = window._FIREBASE_CONFIG || {};

                if (!config.apiKey || !config.authDomain) {
                    setMessage('Konfigurasi Firebase belum tersedia. Hubungi administrator.', true);

                    return;
                }

                const { initializeApp } = await import('https://www.gstatic.com/firebasejs/12.0.0/firebase-app.js');
                const {
                    getAuth,
                    isSignInWithEmailLink,
                    signInWithEmailLink,
                    updatePassword,
                } = await import('https://www.gstatic.com/firebasejs/12.0.0/firebase-auth.js');

                const app = initializeApp(config);
                const auth = getAuth(app);
                const currentUrl = window.location.href;

                if (!isSignInWithEmailLink(auth, currentUrl)) {
                    setMessage('Tautan aktivasi tidak valid atau sudah kedaluwarsa. Minta ulang undangan kepada administrator.', true);

                    return;
                }

                let email = window.localStorage.getItem('emailForSignIn');

                if (!email) {
                    email = window.prompt('Masukkan kembali alamat email yang menerima undangan aktivasi ini:') || '';
                }

                await signInWithEmailLink(auth, email, currentUrl);
                window.localStorage.removeItem('emailForSignIn');

                const url = new URL(currentUrl);

                for (const key of ['apiKey', 'oobCode', 'mode', 'lang', 'continueUrl']) {
                    url.searchParams.delete(key);
                }

                window.history.replaceState({}, '', url.toString());

                setMessage('Email berhasil diverifikasi. Lengkapi data di bawah untuk mengaktifkan akun.');
                formElement?.classList.remove('hidden');
            } catch (error) {
                setMessage('Gagal memproses tautan aktivasi: ' + (error?.message || 'terjadi kesalahan tak terduga.'), true);
            }
        })();

        formElement?.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submitButton = document.getElementById('submit-button');
            const name = document.getElementById('input-name')?.value.trim() || '';
            const password = document.getElementById('input-password')?.value || '';
            const confirmation = document.getElementById('input-password-confirm')?.value || '';

            if (name.length < 3) {
                setMessage('Nama lengkap minimal 3 karakter.', true);

                return;
            }

            if (password.length < 8) {
                setMessage('Kata sandi baru minimal 8 karakter.', true);

                return;
            }

            if (password !== confirmation) {
                setMessage('Konfirmasi kata sandi tidak cocok.', true);

                return;
            }

            try {
                submitButton?.setAttribute('disabled', 'disabled');
                setMessage('Menyimpan data akun...');

                const { getAuth, updatePassword } = await import('https://www.gstatic.com/firebasejs/12.0.0/firebase-auth.js');
                const auth = getAuth();

                if (auth.currentUser) {
                    await updatePassword(auth.currentUser, password);
                }

                const idToken = await auth.currentUser?.getIdToken(true);

                const response = await fetch(window._ACTIVATION_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ id_token: idToken, name: name }),
                });

                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(payload?.error || 'Aktivasi akun gagal diproses.');
                }

                setMessage('Akun berhasil diaktivasi. Mengalihkan...');
                window.location.href = '/admin';
            } catch (error) {
                submitButton?.removeAttribute('disabled');
                setMessage('Aktivasi gagal: ' + (error?.message || 'terjadi kesalahan tak terduga.'), true);
            }
        });
    </script>
</body>
</html>
