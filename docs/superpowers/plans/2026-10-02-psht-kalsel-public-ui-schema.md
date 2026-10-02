# Public UI + Publication Schema Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Use isolated feature worktree; checklist steps use checkbox syntax.

**Goal:** Port the approved public preview design into the Laravel MVP and add backward-compatible publication fields/workflows for optional event covers, gallery categories, and ordered album media.

**Architecture:** Preserve server-rendered Blade and existing Laravel publication/policy/query boundaries. Add only nullable/defaulted publication columns, enum/accessors, explicit Filament event/gallery edit surfaces and gallery media relation management. Event cover files stay private-at-rest and are streamed only for published events. Gallery media uploads start private and only move one-way to public after the parent album is published; application discovery is query/route gated, with explicit disclosure that direct `/storage` URLs cannot be revoked.

**Tech Stack:** PHP 8.5, Laravel 13.34, Blade, Filament 5.9, Tailwind CSS 4.3 via Vite, Alpine.js/browser APIs, Leaflet 1.9.4 already installed, PHPUnit, SQLite test DB.

## Global Constraints

- No new NPM or Composer dependencies.
- Use published queries for public pages; training locations additionally require `verified_at IS NOT NULL`.
- Keep cover event optional; no fallback image if `cover_path` is null. Existing event publish lifecycle must not require cover.
- Gallery category values are exactly `latihan`, `kegiatan`, `pembinaan`; legacy null remains supported and appears only in `all` filter until manually classified.
- Gallery media order is `sort_order ASC, id ASC`; do not backfill guessed order.
- Event covers remain on `local` disk and are never rendered as direct paths. Gallery media uploads start on `local`; publish action moves them to unique `public` paths only when parent Gallery is published. No UI path returns already-published media to local or deletes its public file.
- Public gallery application routes only return `is_public=true`, disk `public`, published parent media. Direct `/storage` URL permanence is an explicit known limitation, not a revoked-access promise.
- Only JPEG/PNG/WebP image uploads, 5 MiB maximum; reject SVG.
- Preserve homepage hero, carousel hooks, existing published-data limits/empty states, public privacy contracts, hierarchy semantics, and unit-scope authorization.
- No preview sample data/assets are inserted or presented as live production content.

---

## File Map

- Create: `database/migrations/*_add_public_ui_publication_fields.php` — add nullable event cover path/alt text, nullable gallery category, media sort order default/index; reversible only for those columns.
- Create: `app/Enums/GalleryCategory.php` — three fixed values and Indonesian labels.
- Modify: `app/Models/Event.php`, `Gallery.php`, `Media.php` — fillable/casts/relationships and ordered media relation; ensure published event cover endpoint uses safe model scope.
- Modify: `app/Filament/Resources/Events/*` — Event edit route, cover FileUpload/alt text, publication actions; preserve existing create workflow.
- Modify: `app/Filament/Resources/Galleries/*` — Gallery Edit route/category/PublishContent actions; Gallery Media RelationManager create/edit/reorder/lifecycle actions.
- Create: small action/service for one-way media local→public publishing and event cover replacement cleanup, with deterministic storage compensation.
- Modify: `app/Http/Controllers/Public/EventController.php`, `GalleryController.php`, `TrainingLocationController.php`; create event cover controller if keeping media response isolated.
- Modify: `app/Http/Controllers/Public/PublicMediaController.php` — require published + published_at parent on application media route.
- Modify: `routes/web.php` — location show slug and event cover route.
- Modify public views: `layouts/app.blade.php`, `home.blade.php`, `events/index.blade.php`, `events/show.blade.php`, `galleries/index.blade.php`, `training-locations/index.blade.php`, `organization/index.blade.php`, `organization/unit.blade.php`, `pages/show.blade.php`; create `training-locations/show.blade.php`.
- Modify: `resources/css/app.css`; add `resources/js/public-gallery-lightbox.js` only if extracted lightbox behavior improves clarity; include it in existing Vite entry via import.
- Modify/add tests: `PublicPortalTest.php`, `PrivateMediaAccessTest.php`, `PublicationWorkflowTest.php`, focused Filament publication schema/workflow tests.

---

### Task 1: Add Backward-Compatible Publication Schema and Contracts

**Interfaces produced:** `GalleryCategory` enum values/labels; `Gallery::media()` ordered by `sort_order,id`; `Media::isPublic` cast, `sort_order` integer; `Event::cover_path`/`cover_alt_text` nullable; Filament edit surfaces use these fields.

- [ ] Write tests first for nullable existing record compatibility, allowed category values, ordered media default/tie behavior, and failed migration data preservation.
- [ ] Run targeted tests to see expected RED.
- [ ] Add migration with only: nullable `events.cover_path`, nullable `events.cover_alt_text`, nullable `galleries.category` plus DB check constraint for null/3 values, `media.sort_order` unsigned default `0`, index `media(gallery_id,is_public,sort_order,id)`, and index `galleries(status,category,published_at)`.
- [ ] Implement `GalleryCategory` enum with Indonesian labels `Latihan`, `Kegiatan`, `Pembinaan`; add category cast only if enum/null casting matches current model conventions.
- [ ] Update Event/Gallery/Media fillable/casts/relations; keep migration `down()` limited to newly added index/columns.
- [ ] Run migration up/down on SQLite test DB; assert old event/gallery/media records still load with null/default values; test category constraints and media ordering tie-break.
- [ ] Commit schema/model foundation.

### Task 2: Add Filament Event and Gallery Edit Workflows

**Interfaces produced:** Event form persists optional local cover+alt text; Gallery form stores fixed category; Gallery Media RelationManager uploads to local, publishes only after album publication, and cannot make a published public file private again.

- [ ] Add feature tests proving event upload accepts JPEG/PNG/WebP <= 5MiB and rejects SVG/non-image/>5MiB; cover is not required for submit/publish.
- [ ] Add tests proving Gallery form accepts only enum values for new/edit records, legacy null displays "Belum dikategorikan", and media management query/actions are restricted to `OrganizationScope` and owner Gallery.
- [ ] Add tests proving media upload initially stores on local with `is_public=false`, public publish action refuses draft/pending Gallery, requires alt text, moves to unique public path and changes state only after copy success, cleans local only after DB success, compensates copied file on DB failure, and `Hapus dari portal` hides application access but retains public file.
- [ ] Add EditRecord pages to Event and Gallery resources; use `PublishContent` existing actions and never add cover requirement to publication action.
- [ ] Add event FileUpload to private local prefix; validate MIME/size, keep old cover until record update succeeds, expose alt text field only when file path exists; use failure-safe cleanup for replaced/removed old path.
- [ ] Add Gallery category Select with enum labels, preserving existing null in edit state until operator selects value.
- [ ] Add a relationship manager for Gallery `media`: one record per file, upload local, alt text, `sort_order`, create/edit controls; implement explicit `Terbitkan media` and `Hapus dari portal` actions. No direct `disk` or `is_public` editing, no destructive deletion of public file, and no replace operation on published media.
- [ ] Run focused Filament/media tests and existing auth/scope tests.
- [ ] Commit Filament upload/publication workflow.

### Task 3: Implement Public Routes and Published Query Contracts

**Interfaces produced:** named routes `public.training-locations.show`, `public.events.cover`; `EventController` list filter contract, `TrainingLocationController::show`, `GalleryController` category filter contract.

- [ ] Write feature tests for event period filters/default/invalid values, stable ordering/pagination with query preservation, event cover route 404 for draft/unpublished/missing file and success for published cover; verify no direct cover path leaks in HTML.
- [ ] Write tests for GalleryCategory all/category/invalid filters, pagination preservation, only public disk + is_public media loaded ordered by sort_order/id, published_at required on parent. Test `PublicMediaController` direct app route cannot serve draft/unpublished album media.
- [ ] Write tests for valid verified published location detail with organization unit, 404 for missing/draft/pending/unverified slug, no map data on invalid, exact matching slug (no default fallback).
- [ ] Modify EventController allowlisted `period=upcoming|past|all`, default upcoming, published scope, 12/page; eager load cover attributes only. Define `past` as `starts_at < now()`, `upcoming` as `starts_at >= now()`.
- [ ] Modify GalleryController allowlisted `category=all|latihan|kegiatan|pembinaan`, default all; include legacy null only in all; published parent, media eager-load filtered `is_public,disk=public` and order `sort_order,id`; 12/page.
- [ ] Add TrainingLocationController show query via slug with the exact existing published+verified predicate and eager-loaded `organizationUnit`; make index cards link to show route while retaining index map JSON/query.
- [ ] Add event cover controller: use route slug binding; enforce published state; resolve only a validated private local file under configured event-cover prefix; return 404 if path missing; determine response MIME from filesystem content (not request path), set safe inline image disposition and nosniff. Never accept a path from query/user input.
- [ ] Tighten PublicMediaController route predicate to require media `is_public`, `disk=public`, Gallery parent exists, published status and `published_at`. Do not claim the underlying public symlink URL is revoked.
- [ ] Run focused controller/security tests and existing PublicPortal/PublicationWorkflow/PrivateMedia tests.
- [ ] Commit routes/query/access boundary.

### Task 4: Port Public Pages to Blade and Production Assets

**Interfaces consumed:** controllers supply filtered paginators, ordered allowed media, verified locations, event cover route URL, and location organization relation; use `route()` for all links.

- [ ] Update tests first for public page theme/navigation, event cover presence/absence, event filters, gallery filter state, location detail structure/map hooks, and existing homepage contracts; verify expected RED.
- [ ] Shared shell: update public layout with a consistent dark editorial header/footer, responsive mobile `<details>` or accessible menu pattern consistent with existing homepage, active current link via route-aware Blade state, max-width container, focus styles, and one theme across routes. Avoid replacing current home hero/header section.
- [ ] Homepage: keep video hero, values, history, sections, carousel markup/control hooks and existing query limits. Render event cover only if published event has `cover_path`; location cards link to `public.training-locations.show`; preserve all current empty states and member-data exclusions.
- [ ] Event list Blade: render one event card with date, venue, optional cover and descriptive alt; query filter links set `aria-current`/pressed state based on `period`; pagination retains period; empty filter state has a clear recovery link to all/upcoming.
- [ ] Event detail Blade: breadcrumb, title/date/venue/body, optional cover via `route('public.events.cover', $event)`, no map, one H1.
- [ ] Gallery Blade: album cards/media images use only `route('public.media.show', $media)` for application discovery; category filter anchors/buttons preserve query state; pager retains category; empty category result message links to all.
- [ ] Add one shared native `<dialog>` for gallery with close, previous/next, image alt, album/media label from alt_text, and local minimal JS. Dialog CSS uses `position: fixed; inset: 0; margin: auto`, viewport bounds and `object-fit: contain`. Open/close via buttons; Escape/backdrop and trigger focus restoration; navigation list contains only public media from rendered current page/filter. Never emit media IDs/paths for filtered/private media.
- [ ] Location list Blade: linked location cards, current verified/public metadata, existing multi-marker map retained with all selected values escaped/JSON-encoded safely.
- [ ] Create location detail Blade: breadcrumb, one H1, unit/address/schedule/contact only when non-empty, preview-equivalent single marker map and attribution, return link. No map input before controller has exact verified record.
- [ ] Structure Blade: preserve parent vs supervising branch distinction; add preview dark hierarchy treatment without introducing client-side fake hierarchy. Profile Blade: apply editorial reading width, typographic/drop-cap treatment only when body non-empty, internal section links only for real anchors, and no invented history.
- [ ] Add CSS for shared public dark tokens/map detail/dialog focus; preserve auth page contrast (scope public tokens/classes so `/admin` auth views do not change theme unexpectedly).
- [ ] Add minimal public lightbox JS module to Vite entry; restore focus reliably; `prefers-reduced-motion`; no generic router or new dependency.
- [ ] Run focused render tests after each subpage group, then Vite build.
- [ ] Commit Blade and production asset migration.

### Task 5: Complete Regression, Accessibility, and Data-Safety Verification

- [ ] Run `php artisan test --compact` and inspect full summary.
- [ ] Run `vendor/bin/pint --dirty --format agent` and re-run affected PHP tests.
- [ ] Run `npm run build`.
- [ ] Run `git diff --check`.
- [ ] Boot Laravel app using existing dev command; browser-test `/`, `/struktur`, `/galeri`, `/kegiatan`, `/kegiatan?period=...`, event detail, `/tempat-latihan`, valid location detail, and invalid/unpublished location 404.
- [ ] Verify event cover private file route only serves published event and non-public states return 404. Validate no path leakage in HTML; test orphan file/MIME error behavior.
- [ ] Verify media lifecycle: local draft upload inaccessible publicly; publishing requires parent Gallery published; media then appears in rendered page and route; unpublish/`Hapus dari portal` removes it from app rendering/route but leaves the known `/storage` file access limitation explicitly documented; private local media never renders.
- [ ] Browser matrix at 360/768/1280: responsive nav, event cards/filters/paginator, gallery filters/pagination/lightbox geometry/keyboard/focus, structure hierarchy, location list/detail map, profile reading layout; no horizontal overflow.
- [ ] Lighthouse mobile Accessibility/Best Practices/SEO on event list/detail, gallery, location list/detail, structure, profile; fix any failures.
- [ ] Review changed path scope and complete diff. Verify approved preview HTML remains unchanged and production view uses DB-backed values only.
- [ ] Commit verification-driven fixes; do not merge until full suite, build, and browser acceptance pass.

---
