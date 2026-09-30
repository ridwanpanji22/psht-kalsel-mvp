/**
 * Firebase Web authentication glue for the operator panel.
 *
 * Powers two standalone (non-Filament) pages:
 *  - `/admin/login`    → email/password + Google sign-in, exchanges the Firebase
 *                        ID token for a Laravel session via `POST /admin/auth/session`.
 *  - `/admin/activate` → consumes a Firebase email-link invite, sets name/password
 *                        and calls `POST /admin/auth/activate`.
 *
 * The heavy Firebase SDK is loaded lazily (dynamic import) only on pages that opt in
 * through `window._FIREBASE_CONFIG`, so the public site bundle is unaffected.
 */

const SESSION_ENDPOINT = '/admin/auth/session';
const ACTIVATE_ENDPOINT = '/admin/auth/activate';
const REDIRECT_URL = '/admin';

let firebaseModules = null;

async function getFirebase() {
    if (firebaseModules) {
        return firebaseModules;
    }

    const [appModule, authModule] = await Promise.all([
        import('firebase/app'),
        import('firebase/auth'),
    ]);

    firebaseModules = { ...appModule, ...authModule };

    return firebaseModules;
}

function currentConfig() {
    return window._FIREBASE_CONFIG || {};
}

function hasConfig() {
    const config = currentConfig();

    return Boolean(config.apiKey && config.authDomain);
}

async function getClient(config) {
    const { initializeApp, getApps, getApp, getAuth } = await getFirebase();
    const app = getApps().length > 0 ? getApp() : initializeApp(config);

    return getAuth(app);
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function postJson(url, payload) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data?.error || 'Permintaan gagal diproses.');
    }

    return data;
}

function messageHandler(element) {
    return (text, isError = false) => {
        if (!element) {
            return;
        }

        element.textContent = text;
        element.classList.toggle('border-red-300', isError);
        element.classList.toggle('bg-red-50', isError);
        element.classList.toggle('text-red-700', isError);
        element.setAttribute('role', isError ? 'alert' : 'status');
    };
}

function firebaseErrorMessage(error) {
    const code = error?.code || '';

    const messages = {
        'auth/invalid-credential': 'Email atau kata sandi salah.',
        'auth/wrong-password': 'Kata sandi salah.',
        'auth/user-not-found': 'Akun tidak ditemukan atau belum aktif.',
        'auth/user-disabled': 'Akun operator dinonaktifkan. Hubungi administrator.',
        'auth/invalid-email': 'Format email tidak valid.',
        'auth/too-many-requests': 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.',
        'auth/popup-closed-by-user': 'Proses masuk dengan Google dibatalkan.',
        'auth/popup-blocked': 'Jendela masuk Google diblokir peramban. Izinkan pop-up lalu coba lagi.',
        'auth/cancelled-popup-request': 'Proses masuk dengan Google dibatalkan.',
        'auth/unauthorized-domain': 'Domain ini belum diizinkan pada konfigurasi Firebase.',
        'auth/account-exists-with-different-credential': 'Email ini sudah terdaftar dengan metode masuk lain. Masukkan kata sandi untuk menautkan akun.',
        'auth/operation-not-allowed': 'Metode masuk ini belum diaktifkan pada konfigurasi Firebase.',
    };

    return messages[code] || 'Terjadi kesalahan saat masuk. Silakan coba lagi.';
}

async function exchangeSession(auth, idToken) {
    const token = idToken || (await auth.currentUser?.getIdToken(true));

    return postJson(SESSION_ENDPOINT, { id_token: token, _token: csrfToken() });
}

function initLogin() {
    const container = document.querySelector('[data-firebase-login]');

    if (!container) {
        return;
    }

    const showMessage = messageHandler(document.getElementById('login-status'));
    const form = document.getElementById('login-form');
    const googleButton = document.getElementById('google-login-button');
    const submitButton = document.getElementById('login-submit-button');

    const busy = (isBusy) => {
        for (const button of [submitButton, googleButton]) {
            if (!button) {
                continue;
            }

            if (isBusy) {
                button.setAttribute('disabled', 'disabled');
            } else {
                button.removeAttribute('disabled');
            }
        }
    };

    if (!hasConfig()) {
        showMessage('Konfigurasi Firebase belum tersedia. Hubungi administrator.', true);

        return;
    }

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const email = document.getElementById('input-email')?.value.trim() || '';
        const password = document.getElementById('input-password')?.value || '';

        if (!email || !password) {
            showMessage('Email dan kata sandi wajib diisi.', true);

            return;
        }

        try {
            busy(true);
            showMessage('Memeriksa kredensial...');

            const auth = await getClient(currentConfig());
            const { signInWithEmailAndPassword } = await getFirebase();
            const credential = await signInWithEmailAndPassword(auth, email, password);
            const idToken = await credential.user.getIdToken(true);

            await exchangeSession(auth, idToken);
            showMessage('Berhasil masuk. Mengalihkan...');
            window.location.href = REDIRECT_URL;
        } catch (error) {
            busy(false);
            showMessage(error?.message?.startsWith('Permintaan gagal')
                ? 'Akun tidak ditemukan atau belum aktif.'
                : firebaseErrorMessage(error), true);
        }
    });

    googleButton?.addEventListener('click', async () => {
        try {
            busy(true);
            showMessage('Membuka jendela masuk Google...');

            const auth = await getClient(currentConfig());
            const { GoogleAuthProvider, signInWithPopup, fetchSignInMethodsForEmail } = await getFirebase();
            const provider = new GoogleAuthProvider();

            const result = await signInWithPopup(auth, provider);
            const idToken = await result.user.getIdToken(true);

            await exchangeSession(auth, idToken);
            showMessage('Berhasil masuk. Mengalihkan...');
            window.location.href = REDIRECT_URL;
        } catch (error) {
            if (error?.code === 'auth/account-exists-with-different-credential') {
                await handleAccountExists(error);

                return;
            }

            busy(false);
            showMessage(firebaseErrorMessage(error), true);
        }
    });

    async function handleAccountExists(error) {
        showMessage('Email ini sudah terdaftar dengan kata sandi. Masukkan kata sandi untuk menautkan akun.', true);

        const { GoogleAuthProvider, signInWithCredential } = await getFirebase();
        const pendingCredential = GoogleAuthProvider.credentialFromError(error);
        const password = window.prompt('Masukkan kata sandi akun operator Anda untuk menautkan akun Google:') || '';

        if (!password || !pendingCredential) {
            busy(false);

            return;
        }

        try {
            showMessage('Menautkan akun Google...');

            const auth = await getClient(currentConfig());
            const { signInWithEmailAndPassword } = await getFirebase();
            const credential = await signInWithEmailAndPassword(auth, error.customData?.email || '', password);

            await signInWithCredential(auth, pendingCredential);
            const idToken = await credential.user.getIdToken(true);

            await exchangeSession(auth, idToken);
            showMessage('Berhasil masuk. Mengalihkan...');
            window.location.href = REDIRECT_URL;
        } catch (linkError) {
            busy(false);
            showMessage(firebaseErrorMessage(linkError), true);
        }
    }
}

function initActivate() {
    const container = document.querySelector('[data-firebase-activate]');

    if (!container) {
        return;
    }

    const endpoint = window._ACTIVATION_ENDPOINT || ACTIVATE_ENDPOINT;
    const showMessage = messageHandler(document.getElementById('status-message'));
    const form = document.getElementById('activation-form');
    const submitButton = document.getElementById('submit-button');

    if (!hasConfig()) {
        showMessage('Konfigurasi Firebase belum tersedia. Hubungi administrator.', true);

        return;
    }

    (async () => {
        try {
            const auth = await getClient(currentConfig());
            const { isSignInWithEmailLink, signInWithEmailLink } = await getFirebase();
            const currentUrl = window.location.href;

            if (!isSignInWithEmailLink(auth, currentUrl)) {
                showMessage('Tautan aktivasi tidak valid atau sudah kedaluwarsa. Minta ulang undangan kepada administrator.', true);

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

            showMessage('Email berhasil diverifikasi. Lengkapi data di bawah untuk mengaktifkan akun.');
            form?.classList.remove('hidden');
        } catch (error) {
            showMessage('Gagal memproses tautan aktivasi: ' + (error?.message || 'terjadi kesalahan tak terduga.'), true);
        }
    })();

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const name = document.getElementById('input-name')?.value.trim() || '';
        const password = document.getElementById('input-password')?.value || '';
        const confirmation = document.getElementById('input-password-confirm')?.value || '';

        if (name.length < 3) {
            showMessage('Nama lengkap minimal 3 karakter.', true);

            return;
        }

        if (password.length < 8) {
            showMessage('Kata sandi baru minimal 8 karakter.', true);

            return;
        }

        if (password !== confirmation) {
            showMessage('Konfirmasi kata sandi tidak cocok.', true);

            return;
        }

        try {
            submitButton?.setAttribute('disabled', 'disabled');
            showMessage('Menyimpan data akun...');

            const auth = await getClient(currentConfig());
            const { updatePassword } = await getFirebase();

            if (auth.currentUser) {
                await updatePassword(auth.currentUser, password);
            }

            const idToken = await auth.currentUser?.getIdToken(true);

            await postJson(endpoint, { id_token: idToken, name: name });

            showMessage('Akun berhasil diaktivasi. Mengalihkan...');
            window.location.href = REDIRECT_URL;
        } catch (error) {
            submitButton?.removeAttribute('disabled');
            showMessage('Aktivasi gagal: ' + (error?.message || 'terjadi kesalahan tak terduga.'), true);
        }
    });
}

function init() {
    try {
        initLogin();
        initActivate();
    } catch (error) {
        console.error('[firebase-auth]', error);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
