# Spesifikasi Desain: Refinement Preview Halaman Publik PSHT Kalsel

**Tanggal:** 2026-10-02  
**Status:** Menunggu tinjauan spesifikasi tertulis  
**Cakupan:** Preview HTML, CSS, dan JavaScript dalam `preview/`; Blade, routes Laravel, database, dan dependency produksi tidak berubah.

## 1. Tujuan

Memperbaiki tiga bagian preview publik berdasarkan hasil review visual:

1. Card kegiatan memiliki sampul gambar.
2. Tempat latihan memiliki halaman detail khusus yang menampilkan peta lokasi.
3. Modal foto galeri berada tepat di tengah viewport secara horizontal dan vertikal.

## 2. Keputusan Desain

### 2.1 Sampul kegiatan

- `preview/kegiatan.html` menampilkan poster `public/images/hero-martial-arts-poster.webp` pada card kegiatan unggulan dan setiap item daftar kegiatan.
- `preview/kegiatan-detail.html` menampilkan poster yang sama sebagai sampul kegiatan.
- Poster adalah data contoh preview, bukan dokumentasi resmi kegiatan.
- Semua gambar memakai `width`, `height`, `aspect-ratio`, `object-fit: cover`, dan teks alternatif agar ruang gambar stabil dan dapat dipahami pembaca layar.
- Filter kegiatan tetap bekerja terhadap seluruh card tanpa perubahan perilaku.

### 2.2 Detail tempat latihan

- `preview/tempat-latihan.html` menjadi daftar tempat latihan. Setiap item berupa tautan menuju `tempat-latihan-detail.html?id=<id>`.
- `preview/tempat-latihan-detail.html` menampilkan breadcrumb, nama tempat, unit pengelola, alamat, jadwal, catatan data contoh, dan peta Leaflet.
- Data lokasi hanya didefinisikan sekali di JavaScript bersama agar halaman daftar dan detail tidak memiliki data yang berbeda.
- Parameter `id` memilih lokasi. ID yang tidak dikenal menampilkan keadaan "Tempat latihan tidak ditemukan", tidak mengganti diam-diam ke lokasi pertama, dan menyediakan tautan kembali ke daftar.
- Peta detail hanya membuat satu marker untuk lokasi aktif. Scroll-wheel zoom dinonaktifkan agar halaman tetap nyaman digulir.
- Atribusi OpenStreetMap tetap terlihat.

### 2.3 Modal galeri terpusat

- Root cause posisi kiri atas: `.gallery-dialog` memiliki `position: fixed` dari user-agent stylesheet, tetapi aturan proyek menetapkan `margin: 0` dan tidak menetapkan inset terpusat. Hasil ukur saat modal dibuka pada viewport 500 x 844 adalah `x=0`, `y=0`.
- `.gallery-dialog` memakai `position: fixed`, `inset: 0`, dan `margin: auto` agar dialog terpusat pada kedua sumbu.
- Lebar dan tinggi maksimum tetap dibatasi viewport. Konten gambar memakai `object-fit: contain` agar tidak terpotong.
- Tombol tutup, tombol sebelumnya/berikutnya, penutupan dengan Escape, klik backdrop, dan pemulihan fokus ke pemicu tetap dipertahankan.

## 3. Arsitektur dan Aliran Data

- Tidak ada framework atau dependency baru.
- Data kegiatan tetap dikelola oleh markup dan `preview/assets/events.js`.
- Data tempat latihan diekspor dari file JavaScript bersama. Halaman daftar membaca data untuk tautan dan interaksi daftar; halaman detail membaca parameter `id`, mencari satu lokasi, mengisi konten, lalu membuat peta.
- Semua URL antarhalaman menggunakan tautan relatif agar tetap dapat dijalankan oleh server HTTP statis.

## 4. Responsif dan Aksesibilitas

- Card kegiatan satu kolom pada mobile dan dapat menjadi susunan gambar serta teks pada layar lebar.
- Halaman detail lokasi menumpuk konten dan peta pada mobile, lalu memakai dua kolom pada desktop.
- Target interaksi minimal 44px, focus ring tetap terlihat, dan urutan keyboard mengikuti urutan visual.
- Modal tidak melebihi `calc(100dvw - 2rem)` atau `calc(100dvh - 2rem)`.
- Tidak ada horizontal overflow pada viewport 360px, 768px, dan 1280px.

## 5. Penanganan Error

- Jika Leaflet gagal tersedia, informasi teks lokasi tetap tampil; area peta tidak mengarang titik lain.
- Jika query `id` kosong atau tidak cocok, halaman menampilkan keadaan tidak ditemukan dan tidak membuat peta.
- Poster memiliki teks alternatif dan tetap menyisakan struktur card yang dapat dibaca bila gambar gagal dimuat.

## 6. Verifikasi

1. Static contract test memastikan semua card kegiatan dan detail kegiatan memiliki sampul.
2. Static contract test memastikan halaman detail tempat latihan, parameter ID, Leaflet, atribusi, dan tautan dari daftar tersedia.
3. Browser smoke test membuka setiap kegiatan dan detail lokasi, memeriksa marker, konten terpilih, serta dead link.
4. Browser test membuka modal galeri pada mobile dan desktop, lalu memastikan pusat dialog berimpit dengan pusat viewport dengan toleransi 1px.
5. Browser test memastikan Escape menutup modal dan fokus kembali ke card pemicu.
6. Pemeriksaan responsive pada 360px, 768px, dan 1280px memastikan tidak ada horizontal overflow.
7. Lighthouse snapshot pada halaman yang berubah menargetkan Accessibility, Best Practices, dan SEO tanpa temuan gagal.
8. `node --test preview/check-preview.mjs`, Laravel test suite, dan `git diff --check` harus lulus.

## 7. Acceptance Criteria

- Semua card kegiatan yang terlihat memiliki sampul gambar contoh yang proporsional.
- Detail kegiatan menampilkan sampul yang sama.
- Klik tempat latihan membuka detail lokasi yang benar dan peta fokus pada marker lokasi tersebut.
- ID lokasi tidak valid menghasilkan keadaan tidak ditemukan.
- Modal galeri terpusat horizontal dan vertikal pada mobile serta desktop.
- Perilaku filter, pagination, navigasi lightbox, Escape, backdrop, dan focus restoration tidak mengalami regresi.
- Tidak ada perubahan pada `app/`, `routes/`, `resources/views/`, database, atau dependency produksi.
