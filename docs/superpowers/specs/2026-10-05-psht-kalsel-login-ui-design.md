# Spesifikasi Desain: Penyegaran UI Login Operator PSHT Kalsel

**Tanggal:** 2026-10-05
**Status:** Disetujui pengguna pada 2026-10-05
**Cakupan:** Tampilan `/admin/login`; alur Firebase dan route autentikasi tidak berubah.

## 1. Tujuan

Selaraskan login operator dengan visual portal publik PSHT yang sudah diimplementasikan: dark editorial, permukaan gelap, biru PSHT sebagai aksi utama, dan aksen amber untuk identitas/fokus. Pertahankan formulir mudah digunakan pada ponsel dan desktop.

## 2. Keputusan layout

- Breakpoint `md` Tailwind (`768px`) memisahkan komposisi desktop dan mobile.
- Desktop (`≥768px`): komposisi split pilihan B, terpusat pada viewport. Kolom identitas di kiri memuat wordmark teks PSHT, pesan singkat “Satu organisasi. Satu ruang kerja.”, dan penanda “Internal · Operator terdaftar”. Kolom kanan memuat form pada permukaan `psht-surface`.
- Mobile (`<768px`): komposisi pilihan C, kartu form terpusat di atas bidang gelap dengan glow/gradien halus. Sembunyikan kolom desktop; wordmark ringkas masuk ke bagian atas kartu. Tidak memakai foto, video, atau logo/crest yang belum tersedia sebagai aset resmi.
- Gunakan Instrument Sans yang sudah dikirim melalui Vite. Jangan menambah dependency atau font eksternal.
- Gunakan token visual yang ada: background `#0d1117`, deeper background `#0b0f16`, surface `#111722`, aksi primer biru `#0720b0` dengan hover/focus `#2162ed`, aksen amber `#fbbf24`, dan warna teks stone yang memenuhi kontras.
- Tautan “Portal publik” tetap tersedia dan mudah ditemukan, tanpa menambah navigasi panel lain.

## 3. Form dan interaksi

- Pertahankan email, kata sandi, tombol masuk, tombol Google, teks akses operator, dan Google account-link recovery di halaman yang sama.
- Pertahankan semua hook DOM yang dikonsumsi `resources/js/firebase-auth.js` dan feature tests: `data-firebase-login-wrapper`, `data-firebase-login`, `login-form`, `input-email`, `input-password`, `login-submit-button`, `google-login-button`, `login-status`, `link-password-section`, `link-password-form`, `link-password`, `link-google-submit`, dan `link-google-cancel`.
- Pesan progres/sukses memakai status live region; kegagalan memakai `role="alert"` yang sudah ditetapkan JS. Recovery tetap tampil in-flow, dapat diakses keyboard, dengan panel amber pada permukaan gelap. Saat recovery muncul, fokus input password tetap dikelola fungsi JS yang ada.
- Semua input memiliki label terlihat, fokus keyboard jelas, tombol utama/Google mencapai target sentuh minimum 44×44px, dan form tidak overflow pada lebar 320px.
- Tidak menambah “lupa kata sandi”, self-registration, penyedia auth lain, atau perubahan urutan/provider login karena belum ada flow backend/produk yang disetujui.

## 4. Batas auth dan struktur dokumen

- Tidak mengubah Firebase sign-in email/password, Google Sign-In, account-exists recovery/linking, token exchange, CSRF, throttling, session regeneration, onboarding, atau otorisasi backend.
- Tidak mengubah `/admin/activate` dan halaman panel Filament selain halaman login.
- View login tetap satu root Livewire di dalam layout Filament. Jangan menambah `doctype`, `<html>`, `<head>`, `<body>`, atau title kedua.
- Pertahankan `window._FIREBASE_CONFIG`, `@vite` dan render hook Filament untuk aktivasi undangan.
- Styling dibatasi pada wrapper login atau kelas khusus login; jangan mengubah tema panel Filament lain atau portal publik. Warna auth light existing tetap dipakai di halaman aktivasi karena `/admin/activate` di luar cakupan.

## 5. Penerimaan dan verifikasi

1. `/admin/login` tetap merender satu dokumen valid dan satu root Livewire.
2. Semua ID/hook JS di §3 tetap tersedia; konfigurasi Firebase dan hook invite tetap ada.
3. Desktop menunjukkan split branding/form; mobile menunjukkan kartu form di atas latar dark sesuai B/C.
4. Uji Chromium pada 320, 390, 768, 1024, dan 1440px: tanpa horizontal overflow, semua kontrol terlihat dan usable, satu H1.
5. Uji keyboard: urutan fokus email → password → login → Google; indikator focus-visible terlihat; recovery dapat digunakan dengan keyboard.
6. Contrast WCAG AA untuk teks normal dan kontrol; status/error terbaca saat diumumkan assistive technology.
7. Jalankan `AuthViewsRenderTest`, `php artisan test --compact`, Pint, `npm run build`, `git diff --check`, browser smoke `/admin/login`, dan secret scan. Firebase provider smoke dilaporkan terpisah jika konfigurasi lokal tidak tersedia.

## 6. Opsi yang tidak dipilih

- A, panel gelap satu kolom: paling ringkas, tetapi branding dan form tidak terbagi seperti komposisi desktop yang dipilih.
- B untuk semua lebar: split branding/form di ponsel membuat form terlalu sempit.
- C untuk semua lebar: familiar di mobile, namun desktop memakai satu kartu tunggal dan mengurangi bidang branding yang disetujui.

Keputusan: B desktop + C mobile dengan satu palette dark editorial PSHT yang konsisten.
