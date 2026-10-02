# Desain Produksi: Migrasi Public UI PSHT Kalsel dengan Skema Publikasi Lengkap

**Tanggal:** 2026-10-02  
**Status:** Menunggu tinjauan spesifikasi tertulis  
**Cakupan:** UI publik Blade yang dipetakan dari preview yang telah disetujui, skema publikasi untuk cover event/kategori galeri/urutan media, form Filament, media access, routes, dan feature tests. Tidak mencakup modul administrasi, keuangan, evaluasi, atau tes sabuk.

## 1. Tujuan

Menerapkan tampilan dan interaksi preview yang telah disetujui ke portal MVP Blade tanpa menyalin konten contoh preview. Semua konten publik bersumber dari record terbit dan media yang berhak tayang. Skema baru menyediakan kategori album, cover event opsional, dan urutan media.

## 2. Keputusan Produk dan UI

### 2.1 Shared shell

- Subhalaman publik menggunakan satu shared dark editorial shell yang selaras dengan homepage: charcoal/deep navy (`#0d1117`, `#0b0f16`), surface (`#111722`), biru (`#0720b0`, `#2162ed`), amber sebagai aksen, Instrument Sans dari Vite, navigasi desktop/mobile, active state, footer, focus ring, dan responsive container.
- Homepage mempertahankan hero video/poster, struktur section dan carousel yang sudah disetujui. Perubahan homepage terbatas pada penggunaan cover event jika terpasang dan tautan kartu lokasi ke detail.
- Tidak menggunakan Tailwind browser CDN, sample data, atau script preview produksi.

### 2.2 Kegiatan

- Daftar menggunakan hanya event `published` dengan `published_at` terisi; detail hanya diambil berdasarkan slug dari scope yang sama.
- Filter query-string `period=upcoming|past|all`, default `upcoming`; `upcoming` membandingkan `starts_at` dengan waktu aplikasi; `past` membandingkan `starts_at` di masa lampau; `all` tanpa rentang waktu. Query parameter dipertahankan pada pagination.
- Pagination tetap server-side, 12 record per halaman.
- Cover event opsional. Jika terpasang, tampilkan gambar cover. Jika kosong, card tetap menggunakan layout teks tanpa gambar; tidak memakai poster sample sebagai fallback.
- Detail kegiatan tidak menampilkan peta, karena Event belum mempunyai koordinat.

### 2.3 Detail tempat latihan

- Daftar dan detail memakai TrainingLocation yang `published` dan `verified_at` tidak null.
- Route baru bernama `public.training-locations.show`, URL `/tempat-latihan/{slug}`.
- Detail menampilkan H1, unit pengelola, alamat, jadwal, kontak publik bila tersedia, satu marker Leaflet, attribution OSM, dan tautan kembali.
- Slug tidak ada/unpublished/unverified menghasilkan 404, bukan fallback ke lokasi lain; Leaflet tidak dimuat dan peta tidak diinisialisasi sebelum controller memvalidasi lokasi.
- Halaman index tetap mempertahankan peta daftar multi-marker existing; tiap card juga menuju detail.

### 2.4 Galeri

- Daftar album published dengan pagination server 12 album per halaman.
- Filter kategori query-string `category=latihan|kegiatan|pembinaan|all`, default `all`, dipertahankan oleh pagination. Kategori tetap ditampilkan sebagai tab/filter buttons.
- Setiap album memiliki satu kategori enum nullable. `NULL` untuk album lama berarti belum diklasifikasikan: muncul di `all`, tidak muncul dalam kategori tertentu, dan ditampilkan dengan label "Belum dikategorikan" di panel operator.
- Setiap album merender media publik dalam urutan `sort_order` naik lalu `id` naik. Media lokal/private tidak ditampilkan atau dimasukkan ke data lightbox.
- Klik media membuka native `<dialog>` terpusat. Previous/next hanya menavigasi media publik yang lolos filter saat ini. Caption/nama aksesibel berasal dari `alt_text`; alt kosong disajikan sebagai gambar dekoratif hanya bila aman, tetapi upload admin akan mewajibkan alt text untuk media publik.
- Escape, backdrop close, tombol tutup, focus trap native modal, focus restoration, dan tombol keyboard tetap tersedia.

### 2.5 Struktur dan profil

- Struktur mengadopsi visual preview dan mempertahankan hierarki formal, relasi supervising branch yang berbeda dari parent formal, serta daftar hierarkis semantik.
- Halaman profil/sejarah mengadopsi editorial layout, drop-cap, lebar baca, dan navigasi subtopik hanya bila anchor memang tersedia di isi; konten body yang dipublikasikan tetap berasal dari Page, tanpa fakta sejarah yang dibuat oleh UI.
- Empty states mempertahankan isi informatif dalam bahasa Indonesia.

## 3. Skema Publikasi

### 3.1 Events

- Tambah `events.cover_path` nullable string dan `events.cover_alt_text` nullable string.
- Upload cover dilakukan langsung pada Event form Filament, disimpan pada disk `local` di prefix privat khusus event. Jangan menyimpan file event di disk publik sebelum event diterbitkan.
- File dibatasi image JPEG/PNG/WebP; SVG tidak diizinkan. Batas ukuran mengikuti media upload organisasi yang ada, maksimal 5 MiB.
- Cover optional pada draft dan published. Publish flow yang sudah ada tidak mensyaratkan cover. Jika tidak ada cover, tampilan publik tanpa gambar.
- Form mengelola alt text saat cover ada. Validasi mengharuskan alt text untuk cover yang diunggah. File baru ditulis ke path baru terlebih dahulu; cover_path/alt text baru disimpan pada record; file lama dihapus hanya setelah save berhasil. Kegagalan upload/update mempertahankan file lama dan menghapus file baru yang tidak terpakai.
- Route `GET /kegiatan/{event:slug}/cover` bernama `public.events.cover` memeriksa event `published` dan `published_at`, lalu mengirim berkas dari disk local dengan content type terverifikasi dan header aman. Event non-public/tidak ada/berkas hilang menghasilkan 404. Jangan expose `cover_path` ke HTML/response JSON.

### 3.2 Gallery category

- Tambah nullable `galleries.category` (`VARCHAR`) yang hanya menerima enum backed `App\Enums\GalleryCategory`: `latihan`, `kegiatan`, `pembinaan`.
- Null diperbolehkan untuk mempertahankan semua row lama tanpa tebakan atau backfill.
- Tambah index gabungan untuk query daftar publik `(status, category, published_at)`. Nilai kategori dijaga oleh enum cast dan validasi pada setiap write path; tidak memakai raw database check constraint agar migration konsisten pada MySQL 8 dan SQLite test DB.
- Gallery create/update form mewajibkan salah satu kategori. Record lama kategori null ditampilkan pada UI internal sebagai "Belum dikategorikan"; operator dapat mengklasifikasikan ketika mengedit.
- Tidak ada tabel categories, multi-select, pivot, atau kategori custom.

### 3.3 Media order

- Tambah `media.sort_order` unsigned integer dengan default 0.
- Media album existing mendapat default seragam 0; tie-breaker `id` naik menjaga deterministic order tanpa backfill. Tambah index `(gallery_id, is_public, sort_order, id)` untuk urutan query album.
- Semua upload event cover dan gallery media menerima hanya MIME image JPEG/PNG/WebP, menolak SVG, dan memiliki batas maksimum 5 MiB.
- Gallery Relation Manager membuat upload baru pada disk `local`, `is_public=false`, dengan `alt_text` dan `sort_order` yang dikelola operator. Aksi `Terbitkan media` hanya tersedia setelah Gallery parent berstatus published dan `published_at` terisi; aksi mewajibkan alt text, menyalin JPEG/PNG/WebP ke path baru di disk `public`, memperbarui record ke disk/public dan `is_public=true`, lalu menghapus file local hanya setelah transaksi database berhasil. Jika copy atau update gagal, file public baru dibersihkan dan record/file local tetap utuh. Draft album tidak pernah memiliki file media baru pada public disk.
- Aksi `Hapus dari portal` mengubah `is_public=false` dan menghapus media dari discovery/route aplikasi tetapi tidak menghapus file pada disk `public`, sebab direct `/storage` URL yang telah diketahui tidak dapat dicabut. UI tidak menawarkan toggle kembali ke private. Upload private baru tetap pada local.

### 3.4 Migrations dan rollback

- Migrations additive dan reversible: tambah field nullable/defaulted saja; tidak memodifikasi/menjatuhkan data existing.
- Tidak ada backfill kategori berdasarkan judul album, cover dari poster preview, atau urutan media hasil tebakan.
- Rollback `down()` menghapus kolom baru; penghapusan upload cover tidak dijalankan di migration rollback. Deployment rollback mengembalikan kode terlebih dahulu, lalu migration rollback bila memang diperlukan; backup aplikasi/database/file wajib mengikuti SOP deployment proyek.

## 4. Admin Management

- Event Filament form menambah upload cover dan alt text, preview, MIME/size validation, dan prefix private `local`. Cover optional; publish action existing tidak meminta cover.
- Event Resource mendapat halaman Edit dengan aksi submit-for-review dan publish-after-review memakai `PublishContent` existing; owner tidak dapat menyetujui konten sendiri.
- Gallery Filament form menambah Select kategori enum; existing null ditampilkan sebagai belum dikategorikan.
- Gallery Resource mendapat halaman Edit dengan aksi submit-for-review dan publish-after-review memakai `PublishContent` existing; owner tidak dapat menyetujui konten sendiri.
- Gallery Media Relation Manager mengunggah satu file per Media record ke disk `local`, mengelola alt text/sort_order, memindahkan file satu arah ke path unik `public` hanya pada aksi `Terbitkan media` dan hanya jika album sudah published, serta menyediakan `Hapus dari portal`. File yang sudah di-public-kan tidak dapat diganti/ditimpa atau dipindah kembali ke local; operator membuat record baru untuk mengganti foto. Tidak ada direct edit pada `disk`/`is_public`.
- Tidak membuat generic media catalog atau abstraction upload baru.

## 5. Routes, Query, dan File Map Target

- `routes/web.php`: tambah detail training location dan route event cover `/kegiatan/{event:slug}/cover` yang dikendalikan published scope.
- `TrainingLocationController`: index dan show berbagi query predicate published+verified; show eager-load `organizationUnit`.
- `EventController`: index filter period tervalidasi, pagination 12, cover detail; show eager-load cover metadata. Cover controller melayani private stored cover only after published check.
- `GalleryController`: filter category tervalidasi, pagination 12, eager-load public media ordered `sort_order,id`, tidak membaca media private.
- `PublicMediaController`: perketat akses route untuk `is_public`, disk public, parent Gallery exists, status published dan `published_at` non-null. `/storage` direct access mengikuti sifat public disk dan tidak dipresentasikan sebagai revoked ketika album di-unpublish.
- Filament Event form menambah upload cover dan alt text. Gallery form menambah Select kategori enum. Gallery Media Relation Manager menyediakan upload media dan aksi lifecycle yang memindahkan local ke public satu arah.
- Blade public subpages diselaraskan dengan desain preview: layout, organization, galleries, events index/show, training-location index/show, pages show. Homepage hero tidak didesain ulang.
- Frontend assets production memakai Vite `resources/css/app.css` dan `resources/js/app.js` (serta satu modul JS terfokus jika perlu lightbox/filter); tidak ada dependency baru. Leaflet yang terpasang tetap dipakai dengan marker dan attribution.
- Tests: update PublicPortalTest, PrivateMediaAccessTest; tambah focused tests event cover route, filter pagination, gallery categories/order/lightbox data privacy, training-location detail valid/invalid, and Filament form/relation manager scope.

## 6. Security and Privacy

- Tidak ada data anggota di halaman publik.
- Draft, pending_review, archived, unverified locations, non-public media, media dari disk local, dan event covers sebelum terbit tidak boleh diakses melalui halaman atau endpoint aplikasi.
- Media untuk album yang masih draft/pending review tetap pada local dan tidak memiliki public URL. Album/media yang tidak lagi berstatus public/published hilang dari HTML dan route `public.media.show`; direct `/storage/...` URL dari media yang sebelumnya diterbitkan tetap dapat diakses karena file pada public disk.
- Jangan mengunggah data privat ke disk public. Public disk dipakai hanya untuk media yang operator setujui untuk tayang; unpublish membatasi aplikasi, bukan revoke file URL yang sudah diketahui.
- Query parameters category/period memakai allowlist enum; nilai tidak valid kembali ke default aman `all`/`upcoming` dan tidak masuk SQL raw.
- Popup Leaflet memakai DOM nodes/textContent atau Blade escaping, bukan interpolasi string HTML yang belum di-escape.
- Sampul event disajikan dari disk local melalui controller yang memverifikasi publication status, bukan direct filesystem path.

## 7. Accessibility and Responsive

- Tepat satu H1 per route state; heading structure berurutan.
- Keyboard navigation, focus rings, native dialog focus management, accessible names, descriptive alt text, label filter controls, aria-live state bila hasil filter berubah.
- Map region memiliki accessible label, attribution terbaca, scroll-wheel zoom disabled; detail lokasi tetap memuat semua informasi alamat/jadwal tanpa mengandalkan map.
- No horizontal overflow pada 360, 768, dan 1280px. Respect prefers-reduced-motion pada animasi carousel dan lightbox.

## 8. Verification and Acceptance Criteria

1. Migration up/down berjalan di database test SQLite dan database proyek tanpa kehilangan data publik yang sudah ada.
2. Event cover nullable and private-at-rest; existing publish workflow does not require cover; only event published dapat mengambil cover. MIME invalid/SVG/over-limit ditolak; event tanpa cover tidak menampilkan image element kosong.
3. Gallery enum menerima tepat tiga kategori atau null untuk legacy. Filter `all` menyertakan null; kategori lain hanya record yang cocok. Pagination mempertahankan filter.
4. Galeri menampilkan hanya media public disk dari published album; urutan stabil (`sort_order,id`). Lightbox hanya menerima media yang dirender dan mempertahankan keyboard/focus behavior.
5. Training-location list/detail memakai published+verified predicate; route slug invalid/unpublished/unverified 404; valid detail menampilkan unit dan tepat satu map marker.
6. Struktur membedakan parent formal dan supervising branch; profile content tidak diubah; homepage hero/carousel contracts tetap hidup.
7. `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`, `npm run build`, `git diff --check` lulus.
8. Browser tests pada 360/768/1280 memeriksa semua subhalaman, query filters, paginator, detail valid/invalid, carousel/lightbox modal geometry/keyboard, event cover public/private access, dan no horizontal overflow.
9. Lighthouse Accessibility/Best Practices/SEO dijalankan pada event list/detail, gallery, training list/detail, organization, profile.
10. Tidak ada mock/sample data atau preview assets ditambahkan sebagai isi database/produksi. Tidak ada dependency baru.

## 9. Risks and Rollback

- `cover_path` menyimpan private file path yang hanya controller authorized to expose after publication; test wajib membuktikan direct route denial for non-public states.
- Public gallery media stays on `public` disk. Gallery unpublish removes media from application HTML/route but cannot revoke any previously disclosed direct `/storage` URL; private media stays on local. A future revocation requirement would need private storage and a managed migration of existing files, explicitly out of this design.
- Gallery media publishing is one-way from local to public. `Hapus dari portal` changes application visibility only; it does not guarantee URL revocation or restore private confidentiality for an already public file.
- Media `sort_order` default 0 mempertahankan legacy order via id tie-break, tetapi first edit/reorder can create duplicate positions; deterministic tie-break remains contract.
- Gallery category nullable berarti legacy album tidak muncul pada category-specific views sampai operator classify; all view remains inclusive.
- Upload cleanup must not remove last good cover before successful record update; test failure path.
- Schema rollback safely drops only new columns; content uploaded during the rollout should be backed up/exported before rollback if migration is reversed after operators add covers.
- Feature implementation remains on isolated branch until reviewed; local merge only after tests/build/browser pass and user requests integration.
