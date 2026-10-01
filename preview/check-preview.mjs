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
