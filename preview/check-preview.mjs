import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const previewRoot = dirname(fileURLToPath(import.meta.url));

async function readPreview(file) {
    return readFile(join(previewRoot, file), 'utf8');
}

function countMatches(content, pattern) {
    return [...content.matchAll(pattern)].length;
}

test('preview index exposes the approved shared shell and page navigation', async () => {
    const html = await readPreview('index.html');

    assert.match(html, /<html[^>]+lang="id"/);
    assert.equal(countMatches(html, /<h1\b/g), 1);
    assert.match(html, /@tailwindcss\/browser@4/);
    assert.match(html, /#0d1117/i);
    assert.match(html, /#0720b0/i);
    assert.match(html, /#2162ed/i);

    for (const href of ['struktur.html', 'galeri.html', 'kegiatan.html', 'tempat-latihan.html', 'profil.html']) {
        assert.match(html, new RegExp(`href="${href.replace('.', '\\.')}`));
    }

    assert.doesNotMatch(html, /[—–]/);
});

test('structure preview preserves unit hierarchy and supervision semantics', async () => {
    const html = await readPreview('struktur.html');

    assert.match(html, /id="unit-search"/);
    assert.match(html, /id="organization-tree"/);
    assert.match(html, /Dibina oleh Cabang Banjarmasin/);
    assert.match(html, /data-unit-type="provinsi"/);
    assert.match(html, /data-unit-type="cabang"/);
    assert.match(html, /data-unit-type="ranting"/);
    assert.match(html, /data-unit-type="rayon"/);
    assert.match(html, /data-unit-type="ukm"/);
    assert.match(html, /data-unit-type="komisariat"/);
    assert.match(html, /Data contoh untuk preview/);
    assert.match(html, /aria-live="polite"/);
});

test('gallery preview provides public albums, filters, and an accessible lightbox', async () => {
    const html = await readPreview('galeri.html');

    assert.equal(countMatches(html, /data-gallery-card/g), 8);
    assert.match(html, /data-gallery-filter="all"/);
    assert.match(html, /data-gallery-filter="latihan"/);
    assert.match(html, /data-gallery-filter="kegiatan"/);
    assert.match(html, /data-gallery-filter="pembinaan"/);
    assert.match(html, /<dialog[^>]+id="gallery-dialog"/);
    assert.match(html, /aria-label="Tutup foto"/);
    assert.match(html, /Data contoh untuk preview/);
    assert.doesNotMatch(html, /nomor anggota|alamat rumah|telepon pribadi/i);
});

test('gallery preview provides functional pagination and lightbox navigation controls', async () => {
    const html = await readPreview('galeri.html');

    assert.match(html, /data-gallery-prev/);
    assert.match(html, /data-gallery-next/);
    assert.match(html, /data-gallery-page/);
    assert.match(html, /data-lightbox-prev/);
    assert.match(html, /data-lightbox-next/);
});

test('event previews expose time filters, localized details, and working navigation', async () => {
    const index = await readPreview('kegiatan.html');
    const detail = await readPreview('kegiatan-detail.html');

    assert.match(index, /data-event-filter="upcoming"/);
    assert.match(index, /data-event-filter="past"/);
    assert.match(index, /data-event-filter="all"/);
    assert.equal(countMatches(index, /data-event-status="upcoming"/g), 2);
    assert.equal(countMatches(index, /data-event-status="past"/g), 1);
    assert.match(index, /kegiatan-detail\.html\?slug=latihan-gabungan-provinsi/);
    assert.match(index, /Padepokan PSHT Banjarmasin/);
    assert.match(detail, /href="kegiatan\.html"/);
    assert.match(detail, /id="event-detail-heading"/);
    assert.match(detail, /Latihan Gabungan Tingkat Provinsi/);
    assert.match(detail, /Data contoh untuk preview/);

    assert.equal(countMatches(index, /data-event-cover/g), 3);
    assert.match(index, /src="\.\.\/public\/images\/hero-martial-arts-poster\.webp"/);
    assert.match(detail, /data-event-detail-cover/);

    // Compact rows: status/when live on the <li>, and exactly one cover sits inside the
    // nested event link body (not merely somewhere in the <li>), so filtering hides the
    // whole list item (events.js toggles hidden on the element matched by data-event-status).
    const compactRows = [...index.matchAll(/<li data-event-status="(?:upcoming|past)" data-event-when="(?:upcoming|past)">([\s\S]*?)<\/li>/g)];
    assert.equal(compactRows.length, 2);
    for (const [, row] of compactRows) {
        assert.doesNotMatch(row, /<a\b[^>]*data-event-status/);
        assert.doesNotMatch(row, /<a\b[^>]*data-event-when/);

        // Capture the nested event anchor's opening tag and its body up to the first closing anchor.
        const anchor = [...row.matchAll(/<a\b[^>]*href="kegiatan-detail\.html\?slug=[^"]*"[^>]*>([\s\S]*?)<\/a>/g)];
        assert.equal(anchor.length, 1);
        const linkBody = anchor[0][1];
        assert.equal(countMatches(linkBody, /data-event-cover/g), 1);

        // The cover must not be rescued by anything outside the link: no other cover in the row.
        const outsideLink = row.replace(anchor[0][0], '');
        assert.equal(countMatches(outsideLink, /data-event-cover/g), 0);
    }
});

test('event detail updates summary when slug selects another event', async () => {
    const detail = await readPreview('kegiatan-detail.html');

    assert.match(detail, /data-event-summary-date/);
    assert.match(detail, /data-event-summary-time/);
    assert.match(detail, /data-event-summary-location/);
});

test('event detail includes a location map preview region', async () => {
    const detail = await readPreview('kegiatan-detail.html');
    assert.match(detail, /id="event-map-preview"/);
    assert.match(detail, /aria-label="Peta lokasi kegiatan"/);
});

test('training location preview pins Leaflet, exposes verified samples, and preserves attribution', async () => {
    const html = await readPreview('tempat-latihan.html');

    assert.match(html, /id="training-map"/);
    assert.match(html, /leaflet@1\.9\.4\/dist\/leaflet\.css/);
    assert.match(html, /leaflet@1\.9\.4\/dist\/leaflet\.js/);
    assert.match(html, /sha256-p4NxAoJBhIIN\+hmNHrzRCf9tD\/miZyoHS5obTRR9BMY=/);
    assert.match(html, /sha256-20nQCchB9co0qIjJZRGuk2\/Z9VM\+kNiyxNV1lvTlZBo=/);
    assert.match(html, /OpenStreetMap contributors/);
    assert.equal(countMatches(html, /data-location-id=/g), 4);
    assert.match(html, /Data contoh untuk preview/);
    assert.doesNotMatch(html, /telepon pribadi|nomor anggota/i);
});

test('profile preview is an honest editorial shell without invented history', async () => {
    const html = await readPreview('profil.html');

    assert.equal(countMatches(html, /<h1\b/g), 1);
    assert.match(html, /href="index\.html"/);
    assert.match(html, /href="#gambaran-umum"/);
    assert.match(html, /id="gambaran-umum"/);
    assert.match(html, /max-w-3xl/);
    assert.match(html, /Contoh konten preview, ganti dengan naskah profil terbit/);
    assert.doesNotMatch(html, /(?:berdiri|didirikan) pada tahun \d{4}|memiliki [\d.]+ anggota|meraih penghargaan/i);
});

test('profile opening paragraph uses the editorial drop-cap treatment', async () => {
    const html = await readPreview('profil.html');
    assert.match(html, /class="[^"]*editorial-drop-cap/);
});

test('every preview page has one heading and all local links and assets resolve', async () => {
    const { readdir, stat } = await import('node:fs/promises');
    const { resolve } = await import('node:path');
    const files = (await readdir(previewRoot)).filter((file) => file.endsWith('.html'));

    assert.equal(files.length, 7);

    for (const file of files) {
        const html = await readPreview(file);
        assert.equal(countMatches(html, /<h1\b/g), 1, `${file} must have one H1`);
        assert.doesNotMatch(html, /[—–]/, `${file} must not contain prohibited dash punctuation`);

        for (const [, attribute, value] of html.matchAll(/\b(href|src)="([^"]+)"/g)) {
            if (/^(?:https?:|data:|#|mailto:|tel:)/i.test(value)) continue;
            const pathname = value.split(/[?#]/, 1)[0];
            if (!pathname) continue;
            const target = resolve(previewRoot, pathname);
            assert.ok((await stat(target)).isFile(), `${file} has unresolved ${attribute}: ${value}`);
        }
    }
});
