import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import vm from 'node:vm';

const previewRoot = dirname(fileURLToPath(import.meta.url));

async function readPreview(file) {
    return readFile(join(previewRoot, file), 'utf8');
}

function countMatches(content, pattern) {
    return [...content.matchAll(pattern)].length;
}

// Minimal fake DOM used to execute the real `assets/locations.js` in a vm.
// It implements only the surface that script touches: querySelector,
// createElement, element append/textContent/hidden/href/className, and a
// recording Leaflet stub on `window.L`.
function createFakeDom(selectors, { search = '', leaflet = true } = {}) {
    const mapCalls = { map: 0, tileLayer: 0, markers: [] };

    class FakeElement {
        constructor(tag = 'div') {
            this.tagName = tag.toUpperCase();
            this.children = [];
            this.textContent = '';
            this.hidden = false;
            this.href = '';
            this.className = '';
            this.attributes = {};
        }

        append(...nodes) {
            this.children.push(...nodes);
        }

        setAttribute(name, value) {
            this.attributes[name] = value;
        }
    }

    const elements = new Map();
    for (const selector of selectors) elements.set(selector, new FakeElement());

    const document = {
        querySelector(selector) {
            return elements.has(selector) ? elements.get(selector) : null;
        },
        createElement(tag) {
            return new FakeElement(tag);
        },
    };

    const window = { location: { search }, L: null };
    if (leaflet) {
        window.L = {
            map() {
                mapCalls.map += 1;
                return { setView() { return this; } };
            },
            tileLayer() {
                mapCalls.tileLayer += 1;
                return { addTo() {} };
            },
            marker(position) {
                mapCalls.markers.push(position);
                return { addTo() { return this; }, bindPopup() { return this; }, openPopup() {} };
            },
        };
    }

    const context = vm.createContext({ document, window, HTMLElement: FakeElement, URLSearchParams, encodeURIComponent });

    return { context, elements, mapCalls, FakeElement };
}

function runLocationsScript(source, dom) {
    vm.runInContext(source, dom.context);
}

// Walks a fake element tree collecting every anchor's href.
function collectHrefs(element, found = []) {
    if (Array.isArray(element.children)) {
        for (const child of element.children) {
            if (child && typeof child.href === 'string' && child.href) found.push(child.href);
            collectHrefs(child, found);
        }
    }
    return found;
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

test('training location preview lists shared samples and links each to a detail page', async () => {
    const list = await readPreview('tempat-latihan.html');
    const detail = await readPreview('tempat-latihan-detail.html');
    const script = await readPreview('assets/locations.js');

    assert.match(list, /data-training-location-list/);
    assert.match(list, /locations\.js/);
    assert.doesNotMatch(list, /id="training-map"/);
    assert.doesNotMatch(list, /leaflet@1\.9\.4/);
    assert.match(list, /Data contoh untuk preview/);
    assert.doesNotMatch(list, /telepon pribadi|nomor anggota/i);
    // No JS means no location data; the fallback must not offer dead detail links.
    assert.equal(countMatches(list, /tempat-latihan-detail\.html\?id=/g), 0);

    assert.match(detail, /id="training-map-detail"/);
    assert.match(detail, /data-location-not-found/);
    assert.match(detail, /OpenStreetMap contributors/);
    assert.match(detail, /leaflet@1\.9\.4\/dist\/leaflet\.css/);
    assert.match(detail, /leaflet@1\.9\.4\/dist\/leaflet\.js/);
    assert.match(detail, /sha256-p4NxAoJBhIIN\+hmNHrzRCf9tD\/miZyoHS5obTRR9BMY=/);
    assert.match(detail, /sha256-20nQCchB9co0qIjJZRGuk2\/Z9VM\+kNiyxNV1lvTlZBo=/);
    assert.match(detail, /locations\.js/);
    assert.equal(countMatches(detail, /<h1\b/g), 1);
    // The kicker is state-aware and must not claim verification when the
    // location is missing.
    assert.match(detail, /data-location-kicker/);
    assert.doesNotMatch(detail, /Lokasi terverifikasi/);
    // The single H1 lives outside the hideable detail container so the
    // not-found state still has a visible heading.
    const detailContainer = detail.slice(detail.indexOf('data-location-detail'));
    const h1Index = detail.indexOf('<h1');
    assert.ok(h1Index > -1 && h1Index < detail.indexOf('data-location-detail'), 'H1 must precede the hideable detail container');
    assert.ok(detailContainer.includes('data-location-detail'), 'detail container must exist');

    // Behavioral: execute the real script against a minimal fake DOM.
    const listDom = createFakeDom(['[data-training-location-list]']);
    runLocationsScript(script, listDom);
    const listContainer = listDom.elements.get('[data-training-location-list]');
    const hrefs = collectHrefs(listContainer);
    assert.equal(hrefs.length, 4, 'list renders four detail links from the shared array');
    assert.deepEqual(
        hrefs.map((href) => href.replace('tempat-latihan-detail.html?id=', '')),
        ['bjm', 'bjb', 'martapura', 'pelaihari'],
    );

    const detailSelectors = ['[data-location-detail]', '[data-location-not-found]', '#training-map-detail', '[data-location-name]', '[data-location-kicker]', '[data-location-unit]', '[data-location-address]', '[data-location-schedule]'];

    const validDom = createFakeDom(detailSelectors, { search: '?id=bjb' });
    runLocationsScript(script, validDom);
    assert.equal(validDom.elements.get('[data-location-detail]').hidden, false, 'valid id shows detail');
    assert.equal(validDom.elements.get('[data-location-not-found]').hidden, true, 'valid id hides not-found');
    assert.equal(validDom.elements.get('[data-location-name]').textContent, 'Tempat Latihan Banjarbaru');
    assert.equal(validDom.elements.get('[data-location-kicker]').textContent, 'Lokasi latihan', 'valid id sets the location kicker');
    assert.equal(validDom.elements.get('[data-location-unit]').textContent, 'Cabang Banjarbaru');
    assert.equal(validDom.elements.get('[data-location-address]').textContent, 'Kawasan Mentaos, Banjarbaru');
    assert.equal(validDom.elements.get('[data-location-schedule]').textContent, 'Rabu dan Sabtu, 19.30 WITA');
    assert.equal(validDom.mapCalls.map, 1, 'valid id creates exactly one map');
    assert.equal(validDom.mapCalls.markers.length, 1, 'valid id creates exactly one marker');
    const [markerLatitude, markerLongitude] = validDom.mapCalls.markers[0];
    assert.equal(markerLatitude, -3.4429, 'marker latitude matches the selected location');
    assert.equal(markerLongitude, 114.8322, 'marker longitude matches the selected location');

    for (const search of ['?id=zzz', '']) {
        const missingDom = createFakeDom(detailSelectors, { search });
        runLocationsScript(script, missingDom);
        assert.equal(missingDom.elements.get('[data-location-detail]').hidden, true, `${search || 'no id'} hides detail`);
        assert.equal(missingDom.elements.get('[data-location-not-found]').hidden, false, `${search || 'no id'} shows not-found`);
        assert.equal(missingDom.elements.get('[data-location-name]').textContent, 'Tempat latihan tidak ditemukan', `${search || 'no id'} sets a visible not-found heading`);
        assert.equal(missingDom.elements.get('[data-location-kicker]').textContent, 'Lokasi tidak tersedia', `${search || 'no id'} sets an honest not-found kicker`);
        assert.equal(missingDom.mapCalls.map, 0, `${search || 'no id'} must not create a map`);
        assert.equal(missingDom.mapCalls.markers.length, 0, `${search || 'no id'} must not create a marker`);
    }
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

    assert.equal(files.length, 8);

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
