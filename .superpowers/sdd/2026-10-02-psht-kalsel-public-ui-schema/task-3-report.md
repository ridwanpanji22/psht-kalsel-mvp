# Task 3 Report — Public Routes and Published Query Contracts

**Plan:** `docs/superpowers/plans/2026-10-02-psht-kalsel-public-ui-schema.md`
**Brief:** `.superpowers/sdd/2026-10-02-psht-kalsel-public-ui-schema/task-3-brief.md`
**Date:** 2026-10-02
**Scope:** Public read-path controllers, query contracts, and access boundaries only. No public Blade redesign/CSS (Task 4), no schema/model changes (Task 1), no Filament write workflows (Task 2).

---

## 1. Deliverables

| Artifact | Change |
|---|---|
| `app/Http/Controllers/Public/EventController.php` | `period=upcoming\|past\|all` allowlist (default `upcoming`), published scope, 12/page, `withQueryString()`; selects `cover_path`/`cover_alt_text` (no file data beyond the stored path) |
| `app/Http/Controllers/Public/EventCoverController.php` | **New.** Streams a published event's private-local cover; 404 for draft/unpublished, no cover, missing file, unsafe path, non-image MIME |
| `app/Http/Controllers/Public/GalleryController.php` | `category=all\|latihan\|kegiatan\|pembinaan` allowlist (default `all`); legacy null only under `all`; published parent; media eager-load filtered `is_public` + `disk=public` ordered `sort_order,id`; 12/page with query preservation |
| `app/Http/Controllers/Public/TrainingLocationController.php` | `show(string $slug)` reusing the exact published+verified predicate with eager-loaded `organizationUnit` |
| `app/Http/Controllers/Public/PublicMediaController.php` | Tightened predicate: media `is_public` + `disk=public` + album exists + status `Published` + `published_at` non-null |
| `routes/web.php` | `public.events.cover` (`/kegiatan/{event:slug}/cover`), `public.training-locations.show` (`/tempat-latihan/{slug}`) — existing route names/contracts untouched |
| `resources/views/public/training-locations/show.blade.php` | **New** minimal detail view (no map data) |
| `resources/views/public/training-locations/index.blade.php` | Cards link to the show route; map JSON/query retained |
| `tests/Feature/PublicRoutesQueryContractTest.php` | **New.** 39 focused feature tests |

No cover_path/disk/private-path is accepted from query/user input anywhere; the cover route binds `{event:slug}` by route model binding and only ever reads `$event->cover_path`.

---

## 2. TDD evidence

### RED (before implementation)

Command: `vendor/bin/phpunit tests/Feature/PublicRoutesQueryContractTest.php`

```
result=failed tests=33 passed=9 failed=12 errors=12

FAILURES:
 - test_events_index_defaults_to_upcoming_published_events
 - test_events_index_period_past_only_returns_started_events
 - test_events_index_period_all_returns_both_without_order_restriction
 - test_events_index_treats_invalid_period_as_default_upcoming
 - test_events_index_orders_upcoming_chronologically_and_paginates_twelve_per_page
 - test_events_index_preserves_period_filter_on_pagination
 - test_gallery_index_defaults_to_all_categories_and_includes_legacy_null
 - test_gallery_index_filters_by_allowlisted_category
 - test_gallery_index_legacy_null_is_excluded_from_specific_categories
 - test_gallery_index_treats_invalid_category_as_default_all
 - test_gallery_index_orders_by_published_at_desc_and_paginates_twelve_while_preserving_category
 - test_public_media_route_returns_404_when_the_album_is_published_without_published_at

ERRORS:
 - test_event_cover_route_streams_a_published_local_cover_as_inline_image :: Route [public.events.cover] not defined.
 - test_event_cover_route_returns_404_for_draft_event_with_a_valid_cover :: Route [public.events.cover] not defined.
 - test_event_cover_route_returns_404_for_published_event_without_a_cover :: Route [public.events.cover] not defined.
 - test_event_cover_route_returns_404_when_the_local_file_is_missing :: Route [public.events.cover] not defined.
 - test_event_cover_route_returns_404_for_an_unsafe_persisted_path :: Route [public.events.cover] not defined.
 - test_training_location_detail_renders_a_verified_published_location_with_its_unit :: Route [public.training-locations.show] not defined.
 - test_training_location_detail_returns_404_for_missing_slug :: Route [public.training-locations.show] not defined.
 - test_training_location_detail_returns_404_for_draft_pending_or_unverified_locations :: Route [public.training-locations.show] not defined.
 - test_training_location_detail_does_not_fall_back_to_a_default_location :: Route [public.training-locations.show] not defined.
 - test_training_location_detail_exposes_no_map_data_for_an_invalid_slug :: Route [public.training-locations.show] not defined.
 - test_training_location_index_cards_link_to_the_detail_route_and_keep_map_payload :: Route [public.training-locations.show] not defined.
```

The 9 passing tests were the pre-existing `show`/`index` behaviors that Task 3 must preserve (event show slug resolution, gallery media privacy, media route basics). All new behavior was RED.

### GREEN (after implementation)

Command: `vendor/bin/phpunit tests/Feature/PublicRoutesQueryContractTest.php`

```
{"tool":"phpunit","result":"passed","tests":33,"passed":33,"assertions":97,"duration_ms":850}
```

Focused suite: **33 tests, 33 passed, 97 assertions, 0 failures, 0 errors.**

### Adjacent regression (Task-3 changed surface + Task 1/2 suites)

```
PublicRoutesQueryContractTest + PublicPortalTest + PublicationWorkflowTest +
PrivateMediaAccessTest + PublicUiPublicationSchemaTest +
FilamentPublicationWorkflowTest + FirebaseAuthEndpointsTest + UnitScopeAuthorizationTest
{"tool":"phpunit","result":"passed","tests":133,"passed":133,"assertions":643}
```

Pint: `{"tool":"pint","result":"passed"}` on the dirty set.

### Anti-vacuous sensitivity checks (mutation testing)

Each mutation was applied, the targeted test observed failing, then reverted from backup:

| Mutation | Target test | Observed |
|---|---|---|
| Remove `published()` check in cover controller | `test_event_cover_route_returns_404_for_draft_event_with_a_valid_cover` | failed |
| Drop `SafeStoragePath` validation (accept raw `cover_path`) | `test_event_cover_route_returns_404_for_an_unsafe_persisted_path` | failed |
| Gallery `resolveCategory` returns raw input (no allowlist) | `test_gallery_index_treats_invalid_category_as_default_all` | failed |
| Media controller drops `published_at !== null` | `test_public_media_route_returns_404_when_the_album_is_published_without_published_at` | failed |
| Location show drops `whereNotNull('verified_at')` | `test_training_location_detail_returns_404_for_draft_pending_or_unverified_locations` | failed |
| Event `resolvePeriod` drops allowlist (invalid → passthrough) | `test_events_index_treats_invalid_period_as_default_upcoming` | failed |
| Inject `{{ $events->first()?->cover_path }}` into `events/index.blade.php` | `test_event_list_html_never_leaks_the_private_cover_path` | failed |

---

## 3. Routes

| Method | URI | Name | Controller | Notes |
|---|---|---|---|---|
| GET | `/kegiatan` | `public.events.index` | `EventController@index` | `period` query, default `upcoming` |
| GET | `/kegiatan/{slug}` | `public.events.show` | `EventController@show` | own slug, published only |
| GET | `/kegiatan/{event:slug}/cover` | `public.events.cover` | `EventCoverController` (invokable) | route model binding on **slug** |
| GET | `/tempat-latihan` | `public.training-locations.index` | `TrainingLocationController@index` | unchanged |
| GET | `/tempat-latihan/{slug}` | `public.training-locations.show` | `TrainingLocationController@show` | published + verified |
| GET | `/galeri` | `public.galleries.index` | `GalleryController@index` | `category` query, default `all` |
| GET | `/media/{media}` | `public.media.show` | `PublicMediaController` | tightened predicate |

Existing route names and URLs are preserved. Route declaration order guarantees the two-segment path `/kegiatan/{event:slug}/cover` (declared after `/kegiatan/{slug}`) never shadows the single-segment `/kegiatan/{slug}` show route, so `/kegiatan/{slug}` still resolves to `public.events.show`; a cover URL needs the extra `/cover` segment.

---

## 4. Privacy matrix

| Resource | State | Public list HTML | Cover/media application route | HTTP result |
|---|---|---|---|---|
| Event | Draft / `published_at` null | excluded | — | — |
| Event | Published, no cover | shown | `/kegiatan/{event:slug}/cover` | **404** |
| Event | Published, cover file present | shown, no path leaked | `/kegiatan/{event:slug}/cover` | 200 inline image, MIME from file content, `nosniff` |
| Event | Published, cover file missing | shown | `/kegiatan/{event:slug}/cover` | **404** |
| Event | Published, unsafe persisted `cover_path` (traversal/absolute/backslash/nul/dot) | shown | `/kegiatan/{event:slug}/cover` | **404** (no cross-prefix file read) |
| Event | Published, cover file not an image | shown | `/kegiatan/{event:slug}/cover` | **404** |
| Event | Published, numeric-looking slug (`12345`) | shown | `/kegiatan/12345/cover` | 200 only when `12345` is that event's slug |
| Event | Published, addressed by numeric DB id | shown | `/kegiatan/{id}/cover` when `{id}` ≠ slug | **404** (binding is on slug) |
| Media | `is_public=false` | excluded | `/media/{id}` | **404** |
| Media | `disk=local` | excluded | `/media/{id}` | **404** |
| Media | `is_public`+`disk=public`, album Draft | excluded | `/media/{id}` | **404** |
| Media | `is_public`+`disk=public`, album Published, `published_at` null | excluded | `/media/{id}` | **404** |
| Media | `is_public`+`disk=public`, album Published w/ `published_at` | shown via `route('public.media.show',$media)` | `/media/{id}` | 200 |
| Media | album hidden after disclosure | excluded from app render/route | `/media/{id}` | **404** (public `/storage` file remains — disclosed-URL permanence, not revoked) |
| Location | missing / Draft / Pending / Published w/o `verified_at` / `verified_at` null | excluded | `/tempat-latihan/{slug}` | **404**, no map payload |
| Location | Published + `verified_at` set | listed, card links to show route | `/tempat-latihan/{slug}` | 200, own slug only (no default fallback) |
| Gallery | legacy `category=null` | only under `category=all` | — | — |

Key guarantees: no `cover_path` / disk / private path appears in any public HTML response; the cover route never accepts a path from query/user input (route-model bound `{event:slug}` only); event/list filters are allowlisted with safe defaults; event lists use a deterministic `starts_at` + `id` tie-break for every period; MIME type comes from the stored bytes, not the filename or request.

---

## 5. Commit

`feat(public): add published public routes and query contracts` — controllers, cover route, routes, location show view/index link, and the focused feature test. See the commit hash in git history for this task.

---

## 6. Concerns

1. **Task 4 must not re-introduce path leaks.** The cover route returns the bytes; the Blade in Task 4 should render `route('public.events.cover', $event)` and never `$event->cover_path`. The leak test guards event list HTML; it does not yet guard a future `events/show` cover render, so Task 4 should extend it.
2. **`public.media.show` is a discovery gate only.** The underlying `public/gallery-media/...` symlink URL stays downloadable once disclosed. This is an accepted plan limitation (documented in `HapusMediaDariPortal`), not a revocation promise.
3. **`categories` filter semantics.** Legacy `category=null` albums appear only under `all`; once Task 4 exposes category filters, operators must classify legacy albums for them to appear under a specific filter (intended per plan).
4. **Map payload has no invalid-slug exposure.** The location show view intentionally renders no map data in Task 3; Task 4's single-marker map must consume the controller bound record only. The existing invalid-slug test asserts no `training-location-data`/`data-map`/coordinate leakage on 404.
5. **No full suite / build / lint mid-flight** per brief; only focused + adjacent PHPUnit suites and Pint on the dirty set were run.

---

## 7. Fix round 1/5

Addressed two review findings. No Blade redesign.

### 7.1 Finding 1 (Important) — cover route must be `GET /kegiatan/{event:slug}/cover`

**Change:** route URI moved from `/kegiatan/{event}/sampul` (id binding) to `/kegiatan/{event:slug}/cover` with an explicit slug binding; `EventCoverController` now enforces published state directly on the slug-bound `Event`. The generic `/kegiatan/{slug}` show route is untouched and still works (the cover route has an extra `/cover` segment, so the two never collide).

**RED (pre-fix code + post-fix tests):** `vendor/bin/phpunit tests/Feature/PublicRoutesQueryContractTest.php`

```
result=failed tests=39 passed=36 failed=3
FAIL test_event_cover_route_url_uses_the_event_slug_not_its_id
  Failed asserting that two strings are identical. Expected '/kegiatan/kegiatan-bersampul/cover' got '/kegiatan/1/sampul'
FAIL test_event_cover_route_streams_a_published_local_cover_as_inline_image
  Expected response status code [200] but received 404.
FAIL test_event_cover_route_resolves_when_the_slug_is_numeric
  Expected response status code [200] but received 404.
```

**GREEN (post-fix):** `vendor/bin/phpunit tests/Feature/PublicRoutesQueryContractTest.php`

```
{"tool":"phpunit","result":"passed","tests":39,"passed":39,"assertions":134}
```

New tests: `test_event_cover_route_url_uses_the_event_slug_not_its_id` (route-generated URL path === `/kegiatan/{slug}/cover`), `test_event_cover_route_streams_a_published_local_cover_as_inline_image` (explicit `/kegiatan/{slug}/cover` resolves), `test_event_cover_route_does_not_resolve_by_numeric_id_when_slug_differs` (`/kegiatan/{id}/cover` 404 when id ≠ slug), `test_event_cover_route_resolves_when_the_slug_is_numeric` (slug `12345` resolves only as that event's slug; another event's numeric id 404s).

### 7.2 Finding 2 (Minor) — deterministic `id` tie-break

**Change:** `EventController::index` orders every period by `starts_at` plus an explicit `id` tie-break: `upcoming` → `starts_at ASC, id ASC`; `past` → `starts_at DESC, id DESC` (recent-first); `all` → `starts_at ASC, id ASC`. This is explicit and cross-driver deterministic.

**Tests** (>12 tied events per period, in `PublicRoutesQueryContractTest`):
`test_events_index_upcoming_breaks_tied_starts_at_by_id_ascending_without_skips_or_duplicates`, `test_events_index_past_breaks_tied_starts_at_by_id_descending_recent_first`, `test_events_index_all_breaks_tied_starts_at_by_id_ascending_deterministically`. Each creates 15 events sharing one `starts_at`, then asserts the exact page-1 ids, exact page-2 ids, empty page intersection, and full 15-id coverage (no duplicate/skip). No implementation-text assertions (only observable ids/order).

**Sensitivity:** a wrong-direction mutation (e.g. `upcoming` using `orderByDesc('id')`) fails the tie test (`result=failed`, 7 assertions, exit non-zero). On SQLite the *correct* explicit direction and its incidental tie order coincide, so removing only the tie-break is not distinguishable on that driver — the tests still fully document and enforce the intended cross-driver contract and would catch a real direction/order regression, duplicate, or skip.

### 7.3 Fix-round verification

Command: `vendor/bin/phpunit PublicRoutesQueryContractTest + PublicPortalTest + PublicationWorkflowTest + PrivateMediaAccessTest + PublicUiPublicationSchemaTest + FilamentPublicationWorkflowTest + FirebaseAuthEndpointsTest + UnitScopeAuthorizationTest`

```
{"tool":"phpunit","result":"passed","tests":139,"passed":139,"assertions":680}
```

Pint on dirty set: `{"tool":"pint","result":"passed"}`. Route list confirms `kegiatan/{event:slug}/cover` (name `public.events.cover`) and generic `kegiatan/{slug}` (name `public.events.show`) coexist. Stable ordering is explicitly present in code.

### 7.4 Commit

`fix(public): bind event cover route on slug and page deterministically` — route URI/binding, `EventCoverController` published check, event `id` tie-breaks, and updated/added tests.
