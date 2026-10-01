# Public Preview Refinements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add event cover images, move training-location maps to dedicated detail pages, and center the gallery lightbox in every supported viewport.

**Architecture:** Keep the preview static and dependency-free beyond its existing Tailwind Browser CDN and Leaflet 1.9.4. Reuse the existing poster for every mock event, use one `locations.js` data source to render both location list and selected detail, and fix the native dialog through CSS positioning rather than JavaScript geometry.

**Tech Stack:** HTML5, CSS, vanilla JavaScript, Node built-in test runner, Leaflet 1.9.4, OpenStreetMap tiles.

## Global Constraints

- Change preview deliverables and preview documentation only; do not modify `app/`, `routes/`, `resources/views/`, database, or production dependencies.
- Reuse `public/images/hero-martial-arts-poster.webp`; do not add or duplicate media.
- Preserve palette, one `<h1>` per page, visible focus states, WCAG AA contrast, and layouts at 360px, 768px, and 1280px.
- Keep OpenStreetMap attribution visible and Leaflet pinned to 1.9.4 with existing SRI values.
- Keep all mock content labelled as preview data; do not introduce private data or unverified claims.
- Keep event filters and gallery pagination, previous/next, Escape, backdrop close, and focus restoration working.

---

### Task 1: Add Event Cover Images

**Files:**
- Modify: `preview/check-preview.mjs`
- Modify: `preview/kegiatan.html`
- Modify: `preview/kegiatan-detail.html`

**Interfaces:**
- Every event link with `data-event-status` contains one `img[data-event-cover]` using `../public/images/hero-martial-arts-poster.webp`.
- Event detail contains one `img[data-event-detail-cover]` using the same source.
- `preview/assets/events.js` continues filtering the existing `data-event-status` elements without API changes.

- [ ] **Step 1: Add failing cover contract checks**

Add assertions to the event preview test:

```js
assert.equal(countMatches(index, /data-event-cover/g), 3);
assert.match(index, /src="\.\.\/public\/images\/hero-martial-arts-poster\.webp"/);
assert.match(detail, /data-event-detail-cover/);
```

- [ ] **Step 2: Run the focused static test and confirm RED**

Run: `node --test --test-name-pattern="event previews" preview/check-preview.mjs`
Expected: FAIL because event cover markers do not exist.

- [ ] **Step 3: Add the minimum responsive cover markup**

Replace the featured card's decorative `map-panel` with a real image. Convert the two compact event rows to image cards while preserving `data-event-status`, `data-event-when`, href, date, title, venue, and status. Each image uses:

```html
<img data-event-cover src="../public/images/hero-martial-arts-poster.webp" width="1600" height="900" class="aspect-video w-full object-cover" alt="Sampul contoh kegiatan ...">
```

Add the same poster below the detail header as `data-event-detail-cover`. Keep the visible preview-data warning.

- [ ] **Step 4: Run event and full static tests**

Run: `node --test --test-name-pattern="event previews" preview/check-preview.mjs`
Expected: PASS.

Run: `node --test preview/check-preview.mjs`
Expected: all tests PASS.

- [ ] **Step 5: Commit event covers**

```bash
git add preview/check-preview.mjs preview/kegiatan.html preview/kegiatan-detail.html
git commit -m "feat(preview): add event cover images"
```

---

### Task 2: Add Dedicated Training Location Details

**Files:**
- Modify: `preview/check-preview.mjs`
- Modify: `preview/tempat-latihan.html`
- Modify: `preview/assets/locations.js`
- Create: `preview/tempat-latihan-detail.html`

**Interfaces:**
- List target: `[data-training-location-list]`; `locations.js` renders its four anchor cards from the shared `locations` array.
- Shared data: `locations` array inside `preview/assets/locations.js`, containing `id`, `name`, `address`, `schedule`, `latitude`, `longitude`, and `unitName`.
- Detail targets: `[data-location-detail]`, `[data-location-not-found]`, `[data-location-name]`, `[data-location-unit]`, `[data-location-address]`, `[data-location-schedule]`, and `#training-map-detail`.
- Detail URL: `tempat-latihan-detail.html?id=<location.id>`.

- [ ] **Step 1: Add failing detail-page contract checks**

Update static checks to expect eight HTML pages and assert:

```js
assert.match(list, /data-training-location-list/);
assert.equal(countMatches(list, /tempat-latihan-detail\.html\?id=/g), 4);
assert.match(detail, /id="training-map-detail"/);
assert.match(detail, /data-location-not-found/);
assert.match(detail, /OpenStreetMap contributors/);
```

- [ ] **Step 2: Run the focused location test and confirm RED**

Run: `node --test --test-name-pattern="training location" preview/check-preview.mjs`
Expected: FAIL because the detail page and links do not exist.

- [ ] **Step 3: Change list page to a shared-data location list**

Remove Leaflet assets and the inline `#training-map` from `tempat-latihan.html`. Replace the four hard-coded buttons with an empty `[data-training-location-list]` container. In `locations.js`, render one accessible anchor per `locations` item targeting `tempat-latihan-detail.html?id=${location.id}` and containing its unit, name, address, and schedule. Update the heading copy to explain that selecting a location opens address, schedule, and map details.

- [ ] **Step 4: Build the detail document**

Create `tempat-latihan-detail.html` with the shared preview shell, breadcrumb back to `tempat-latihan.html`, one `<h1 data-location-name>`, labelled unit/address/schedule fields, preview-data warning, `[data-location-not-found hidden]`, Leaflet 1.9.4 CSS/JS with existing SRI, `#training-map-detail`, and visible OpenStreetMap attribution.

- [ ] **Step 5: Reuse location data for detail rendering**

Keep the `locations` array as the only location-data definition in `locations.js`. If `[data-training-location-list]` exists, render the list anchors from that array. On a page with `#training-map-detail`, read `new URLSearchParams(location.search).get('id')`, find an exact ID, and either populate all detail targets and create one marker or show `[data-location-not-found]` while hiding `[data-location-detail]`. Never default an invalid ID to the first location.

- [ ] **Step 6: Run focused and full static tests**

Run: `node --test --test-name-pattern="training location" preview/check-preview.mjs`
Expected: PASS.

Run: `node --test preview/check-preview.mjs`
Expected: all tests PASS.

- [ ] **Step 7: Commit location details**

```bash
git add preview/check-preview.mjs preview/tempat-latihan.html preview/tempat-latihan-detail.html preview/assets/locations.js
git commit -m "feat(preview): add training location detail maps"
```

---

### Task 3: Center the Gallery Lightbox

**Files:**
- Modify: `preview/check-preview.mjs`
- Modify: `preview/assets/preview.css`

**Interfaces:**
- Existing `#gallery-dialog`, `gallery.js`, keyboard behavior, and focus restoration remain unchanged.
- CSS positioning contract: `position: fixed; inset: 0; margin: auto;`.

- [ ] **Step 1: Add a failing CSS contract check**

Read `assets/preview.css` in the gallery test and assert the `.gallery-dialog` block contains fixed positioning, zero inset, and automatic margin.

- [ ] **Step 2: Run the gallery test and confirm RED**

Run: `node --test --test-name-pattern="gallery preview" preview/check-preview.mjs`
Expected: FAIL because `.gallery-dialog` currently uses the user-agent fixed position with project `margin: 0`.

- [ ] **Step 3: Apply the CSS root-cause fix**

Set:

```css
.gallery-dialog {
    position: fixed;
    inset: 0;
    margin: auto;
    overflow: auto;
}
```

Retain existing width, max-height, border, background, color, and padding rules.

- [ ] **Step 4: Run gallery and full static tests**

Run: `node --test --test-name-pattern="gallery preview" preview/check-preview.mjs`
Expected: PASS.

Run: `node --test preview/check-preview.mjs`
Expected: all tests PASS.

- [ ] **Step 5: Commit modal positioning**

```bash
git add preview/check-preview.mjs preview/assets/preview.css
git commit -m "fix(preview): center gallery lightbox"
```

---

### Task 4: Browser and Regression Verification

**Files:**
- Modify only if verification exposes a requirement violation in Task 1-3 files.

**Interfaces:**
- Static preview URL: `http://127.0.0.1:8173/preview/index.html`.

- [ ] **Step 1: Run complete automated checks**

Run:

```bash
node --test preview/check-preview.mjs
php artisan test --compact
git diff --check
```

Expected: preview tests all PASS, Laravel suite PASS, and no whitespace errors.

- [ ] **Step 2: Verify event covers in browser**

Open `kegiatan.html` at 360px, 768px, and 1280px. Verify three visible event cover elements across the All filter, correct links, detail cover, and no horizontal overflow.

- [ ] **Step 3: Verify every location detail**

Open all four `tempat-latihan-detail.html?id=...` URLs. Verify selected name, address, schedule, one Leaflet marker, tiles, attribution, and back link. Open `?id=unknown`; verify not-found state and zero map initialization.

- [ ] **Step 4: Verify gallery centering and keyboard behavior**

At 360px and 1280px, open first gallery image and compute dialog center against viewport center. Require absolute x/y difference <= 1px. Press Escape and verify dialog closes and focus returns to the triggering button. Verify previous/next still changes caption.

- [ ] **Step 5: Run accessibility audits**

Run Lighthouse mobile snapshots on `kegiatan.html`, one valid training detail, and `galeri.html`. Require no failed Accessibility, Best Practices, or SEO audits.

- [ ] **Step 6: Final branch check**

Confirm changed production paths remain untouched and branch contains only preview files plus approved spec/plan. Do not merge or modify Blade.
