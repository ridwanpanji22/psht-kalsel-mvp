# Task 2 Report — Filament Event and Gallery Edit Workflows

**Plan:** `docs/superpowers/plans/2026-10-02-psht-kalsel-public-ui-schema.md`
**Brief:** `.superpowers/sdd/2026-10-02-psht-kalsel-public-ui-schema/task-2-brief.md`
**Design:** `docs/superpowers/specs/2026-10-02-psht-kalsel-public-ui-schema-design.md`
**Date:** 2026-10-02
**Commit (HEAD subject, stable identifier):** `feat(public): add Filament event cover and gallery media workflows` (the report is folded into this commit, so the embedded hash is the pre-fold commit; the commit subject above is the stable identifier)
**Scope:** Filament admin write surfaces only (Event cover, Gallery category, Gallery media relation manager). No controllers, public Blade, or routes (Tasks 3/4). No new dependencies.

---

## 1. Deliverables

| Artifact | Change |
|---|---|
| `app/Filament/Resources/Events/Pages/EditEvent.php` | New EditRecord page + `submitForReview` / `publishReviewedContent` actions via `PublishContent`; custom `handleRecordUpdate` routes the cover through `AturSampulEvent` |
| `app/Filament/Resources/Events/Schemas/EventForm.php` | Private `local` cover `FileUpload` (+ alt-text field shown/required only when a cover path exists) |
| `app/Filament/Resources/Events/EventResource.php` | Registers `edit` page |
| `app/Models` (unchanged) | Reused Task-1 `cover_path`/`cover_alt_text`, `category`, `media.sort_order` |
| `app/Filament/Resources/Galleries/Pages/EditGallery.php` | New EditRecord page + `PublishContent` actions |
| `app/Filament/Resources/Galleries/GalleryResource.php` | Registers `edit` page and `GalleryMediaRelationManager` |
| `app/Filament/Resources/Galleries/RelationManagers/GalleryMediaRelationManager.php` | New relation manager: upload-local, alt text, sort_order, `Terbitkan media`, `Hapus dari portal`; no `disk`/`is_public` editing |
| `app/Actions/AturSampulEvent.php` | Failure-safe event cover file lifecycle |
| `app/Actions/PublishMediaToPortal.php` | One-way local→public media transition with copy/DB/cleanup ordering |
| `app/Actions/HapusMediaDariPortal.php` | Application-only visibility removal (never deletes public file) |
| `app/Policies/MediaPolicy.php` | Added unit-scoped `create`/`update`/`delete`/`deleteAny` |
| `tests/Feature/FilamentPublicationWorkflowTest.php` | 23 new Livewire feature/behavior tests |

### APIs used (Filament 5.9 / Laravel 13.34 / Livewire 4.4.7)

- `Filament\Resources\Pages\EditRecord` with `handleRecordUpdate(Model, array)` override.
- `Filament\Actions\Action` (`visible`, `schema`, `requiresConfirmation`, `modalDescription`, `action`, `successNotificationTitle`, `icon`, `label`).
- `Filament\Actions\CreateAction` with `using(Closure)`; `Filament\Actions\EditAction`.
- `Filament\Forms\Components\FileUpload` (`disk`, `directory`, `visibility`, `image`, `acceptedFileTypes`, `maxSize`, `preserveFilenames`).
- `Filament\Forms\Components\TextInput` (`numeric`, `integer`, `minValue`, `default`, `required`); `Filament\Schemas\Components\Utilities\Get`.
- `Filament\Resources\RelationManagers\RelationManager` with `form(Schema)`, `table(Table)`, `canViewForRecord`, `modifyQueryUsing`, `headerActions`, `recordActions`.
- `Filament\Tables\Columns\{TextColumn,IconColumn}`; `Filament\Tables\Table` (`defaultSort`).
- `Illuminate\Validation\ValidationException::withMessages`; `Illuminate\Support\Facades\{DB,Storage}`.
- Reused: `App\Actions\PublishContent`, `App\Enums\GalleryCategory` via `CategorySchema::field()`, `App\Support\OrganizationScope`, existing `EventPolicy`/`GalleryPolicy`.
- Test APIs: `Livewire::test(...)`, `fillForm`, `call('save')`, `assertHasNoFormErrors`, `assertHasFormErrors`, `assertFormSet`, `assertActionVisible`, `callAction`, `callTableAction`, `assertTableActionHidden`, `assertCanSeeTableRecords`/`assertCanNotSeeTableRecords`, `mountTableAction`, `assertFormFieldDoesNotExist`, `assertHasTableActionErrors`/`assertHasNoTableActionErrors`; `Storage::fake`, `UploadedFile::fake`.

---

## 2. Code / data lifecycle

### 2.1 Event cover (private-at-rest)

- Form: `FileUpload('cover_path')->disk('local')->directory('private/event-covers')->visibility('private')->image()->acceptedFileTypes(jpeg,png,webp)->maxSize(5120)`.
- `cover_alt_text` is `visible`/`required` only when `fill($get('cover_path'))` — hidden with no cover, so a coverless draft/published event passes validation and publishing never requires a cover.
- On save, `EditEvent::handleRecordUpdate` strips `cover_path`/`cover_alt_text` from the mass-updatable payload and delegates to `AturSampulEvent::apply()`:
  1. `$event->update([...cover_path, cover_alt_text])`.
  2. On throw → delete the **new** local file, leave the old cover untouched, rethrow.
  3. On success → delete the **old** path only if it differs.
- The record's other fields are filled and saved before the cover lifecycle, so a broken cover write cannot lose unrelated edits.

### 2.2 Gallery category

- `GalleryForm` reuses `CategorySchema::field()` (single source of truth for allowed enum values/labels). Legacy `category = null` hydrates unset and displays the "Belum dikategorikan" placeholder until the operator selects a value.

### 2.3 Gallery media (one-way local → public)

- Upload (`CreateAction`): writes to `local` under `private/gallery-media`, creates one `Media` row with `disk='local'`, `is_public=false`, `alt_text`, `sort_order`, and copies `mime_type`/`size` read back from the stored file. `file` is `dehydrated` only on create, so editing cannot replace/tamper with the file.
- `Terbitkan media`:
  - Hidden unless `!$record->is_public && isOwnerPublished()` (parent status `Published` **and** `published_at` non-null).
  - Schema requires `alt_text`; the action passes it to the service which re-validates.
  - Service order: guard (already public / blank alt / no parent / parent not published / source missing) → `put` copy to unique `public/gallery-media/...` path → `DB::transaction` update (`disk=public`, `path=publicPath`, `alt_text`, `is_public=true`) → only after commit delete the source local file. On copy failure or DB failure, the freshly copied public file is deleted and the local file is retained.
- `Hapus dari portal`: visible only for public records; sets `is_public=false` and never touches the file on the public disk (documented irreversibility of disclosed `/storage` URLs).
- Relation query is hard-scoped to `gallery_id = ownerRecord`; `canViewForRecord` requires `update` on the owner Gallery (unit-scoped), so out-of-unit operators never see or act on the manager.

### 2.4 Authorization

- `MediaPolicy`: `create` requires an active user with a non-empty visible-unit set; `update` reuses the unit-scoped `view` decision; `delete`/`deleteAny` deny. This keeps media management unit-scoped and satisfies Filament's strict authorization mode for the relation manager actions.

### 2.5 `OrganizationScope` reuse

No new scope logic: Event/Gallery forms continue to populate unit selects from `OrganizationScope::visibleUnitIds()`, and all record-level access flows through the existing `EventPolicy`/`GalleryPolicy`/`MediaPolicy` unit checks.

---

## 3. TDD evidence

### RED (before implementation)

Command: `php artisan test --compact tests/Feature/FilamentPublicationWorkflowTest.php`

```
{"tool":"phpunit","result":"failed","tests":20,"passed":0,"assertions":1,
 "duration_ms":1354,"failed":1,"errors":19}
```

All 19 errors were missing classes (`EditEvent`, `EditGallery`,
`GalleryMediaRelationManager`, `PublishMediaToPortal`, `HapusMediaDariPortal`) and
the one failure was the not-yet-registered Gallery edit route returning 404 instead of
403. Every asserted behavior was RED.

### GREEN (after implementation)

Command: `php artisan test --compact tests/Feature/FilamentPublicationWorkflowTest.php`

```
{"tool":"phpunit","result":"passed","tests":23,"passed":23,"assertions":152,"duration_ms":4200}
```

Focused suite: **23 tests, 23 passed, 152 assertions, 0 failures, 0 errors.**

Adjacent regression check (Task-1 schema, private-media access, publication workflow,
Filament auth, unit isolation, unit-scope authorization, membership scope):

```
{"tool":"phpunit","result":"passed","tests":54,"passed":54,"assertions":256,"duration_ms":5881}
```

Pint: `{"tool":"pint","result":"passed"}` on the dirty set.

### Anti-vacuous checks (test sensitivity)

Each mutation was applied, the targeted test observed failing, then reverted:

| Mutation | Target test | Observed |
|---|---|---|
| Removed `&& $this->isOwnerPublished()` from `Terbitkan media` visibility | `test_publish_media_action_is_hidden_and_refused_for_a_draft_gallery` + `..._hidden_for_already_public_media` | 1 failure |
| Disabled `Storage::disk($sourceDiskName)->delete($sourcePath)` | `test_publishing_media_moves_the_file_to_a_unique_public_path_and_flips_state` | 1 failure |
| Disabled the DB-failure compensation `$publicDisk->delete($publicPath)` | `test_publishing_media_compensates_the_copied_public_file_when_the_database_update_fails` | 1 failure |

All mutations reverted; the focused suite returned to green (23/23).

---

## 4. Test coverage map (brief → test)

| Brief requirement | Test |
|---|---|
| Event upload accepts JPEG/PNG/WebP ≤ 5 MiB on private local | `test_event_edit_accepts_jpeg_png_and_webp_covers_on_the_private_local_disk` |
| Rejects SVG/non-image/>5 MiB | `test_event_cover_upload_rejects_svg_non_image_and_oversized_files` |
| Cover not required for submit/publish | `test_event_without_a_cover_can_be_submitted_and_published` |
| Old cover kept until update succeeds; safe cleanup | `test_atur_sampul_action_keeps_the_old_file_until_the_new_path_is_persisted_then_removes_it`, `test_atur_sampul_action_keeps_the_old_file_when_the_record_update_fails`, `test_replacing_a_missing_orphaned_event_cover_writes_the_new_file_and_clears_the_old_path` |
| Alt text required only when cover present | `test_event_cover_alt_text_is_required_when_a_cover_is_present` |
| Clear cover clears alt text + deletes file | `test_atur_sampul_action_clears_alt_text_when_the_cover_is_removed` |
| Gallery enum only accepted; legacy null shown unset | `test_gallery_edit_accepts_enum_category_and_preserves_legacy_null_on_load`, `test_gallery_edit_rejects_category_outside_the_fixed_enum` |
| Gallery edit + PublishContent actions | `test_gallery_edit_actions_use_existing_publish_content_workflow` |
| Owner-Gallery scope enforcement | `test_gallery_edit_page_is_denied_for_an_operator_outside_the_owner_unit`, `test_media_relation_manager_query_only_lists_media_of_the_owner_gallery`, `test_media_relation_manager_is_hidden_for_an_operator_outside_the_owner_unit` |
| Upload stores local + `is_public=false` | `test_media_upload_stores_on_local_private_disk_and_never_on_public` |
| Publish refuses draft/pending album | `test_publish_media_action_is_hidden_and_refused_for_a_draft_gallery` |
| Publish requires alt text | `test_publish_media_requires_alt_text` |
| Unique public path + state after copy success | `test_publishing_media_moves_the_file_to_a_unique_public_path_and_flips_state`, `test_publishing_media_uses_a_unique_path_even_for_identical_source_names` |
| Compensate copied file on DB failure | `test_publishing_media_compensates_the_copied_public_file_when_the_database_update_fails` |
| `Hapus dari portal` retains public file | `test_hapus_dari_portal_hides_the_media_but_keeps_the_public_file` |
| No re-publish / one-way | `test_publish_media_action_is_hidden_for_already_public_media` |
| No `disk`/`is_public` editing | `test_media_relation_manager_form_cannot_edit_disk_or_is_public` |

---

## 5. Self-review

- **Scope discipline:** only Task-2 files changed. No controllers, public views, routes,
  or migrations. No dependencies added; `composer.json`/`package.json` untouched.
- **Lifecycle ordering honored:** public file copied before state flip; DB commit before
  local-file deletion; compensation delete on DB failure; old event cover deleted only
  after a successful record update.
- **Authorization unit-scoped:** all reads/writes funnel through existing unit-scoped
  policies; the relation manager additionally hard-scopes by `gallery_id` and gates
  visibility on `update` of the owner Gallery.
- **No cover requirement in publication:** `submitForReview`/`publishReviewedContent`
  depend only on `PublicationStatus` and `submitted_by_id`, mirroring `EditPage`.
- **Single source of truth for category:** `CategorySchema::field()` reused verbatim.
- **No destructive public deletion:** `Hapus dari portal` never removes the public file;
  `delete`/`deleteAny` deny on media.
- **Convention parity:** `PublishContent` action style, Indonesian labels, `#[Fillable]`
  model conventions, `LazilyRefreshDatabase` + `Livewire::test` test style.
- **Determinism/formatting:** Pint clean; no stray debug files remain
  (verified `git status` is clean apart from the intended commit).

---

## 6. Concerns / notes

1. **Livewire file-replacement testing limitation (test-harness only).** Filament 5's
   `FileUpload` keeps an existing stored file under a UUID state key; Livewire's `set`
   with a bare `UploadedFile` *appends* a new key instead of replacing the old one, and
   `fillForm` re-hydrates the existing key. The realistic replacement path (old file
   missing/orphaned on disk) is exercised through the Livewire Edit page
   (`test_replacing_a_missing_orphaned_event_cover_...`), while the steady-state
   replace-and-delete lifecycle is proven directly through `AturSampulEvent` with storage
   assertions. This is a framework test ergonomics issue, not app behavior; no
   production code works around it.
2. **`write` vs `update` for cover/size.** Media `mime_type`/`size` are derived from the
   stored file with `rescue(..., report: false)` so a metadata probe failure degrades to
   `null` rather than blocking the upload. The columns are nullable per Task 1.
3. **`disk` observation in the table.** The relation table displays a `disk` badge and
   `is_public` icon (read-only observability). Brief intent ("no direct `disk`/`is_public`
   *editing*") is preserved — neither appears in the create/edit form.
4. **Unused `$actor` parameters.** `PublishMediaToPortal::publish` and
   `HapusMediaDariPortal::hide` accept the acting `User` for API symmetry with
   `PublishContent` and as the anchor for future audit events; no audit row is written in
   Task 2 (audit for media transitions is not in the brief).
5. **Public-file URLs remain non-revocable by design.** As the design states, a
   previously disclosed direct `/storage` URL cannot be revoked; `Hapus dari portal` only
   removes application discovery. This is intended, not a defect.
6. **No Event/Gallery `Replicate`/delete surfaces added.** Policies still deny delete;
   unchanged from prior tasks.
7. **Not run (per brief):** full `php artisan test`, `npm run build`, and whole-repo Pint.
   Focused Task-2 + adjacent auth/scope suites (54 tests) were run and pass.

---

## 7. Fix round 1 — reviewer findings resolved

**Commit:** `c33b11f` — `fix(public): harden Filament media cover and upload lifecycle`
(base: `a4e5252`). All six findings below were resolved with TDD (RED → GREEN) and each
guard was proven non-vacuous by mutation.

### 7.1 Findings and fixes

| # | Severity | Finding | Fix |
|---|---|---|---|
| 1 | Critical | Path tampering: client could submit an existing local path (MIME/size bypass, private-file publish); event `cover_path` same risk | Added `->preventFilePathTampering(true, allowFilePathUsing)` + an explicit prefix `->rules([...])` allowlist to **both** FileUpload fields, and server-side prefix checks in the media create callback (`private/gallery-media/`). `PublishMediaToPortal` now rejects any path outside `private/gallery-media/`; `EditEvent` cover is validated as `private/event-covers/`. |
| 2 | Important | Hidden media (`is_public=false`, `disk=public`) could be republished, copying/deleting the public source | `PublishMediaToPortal` now requires `disk === 'local'` and a `private/gallery-media/` prefix; the `Terbitkan media` action visibility mirrors this. After `Hapus dari portal` no republish is possible, and the `/storage` file remains. |
| 3 | Important + Minor | Upload metadata `rescue(..., report:false)` returned null despite NOT NULL columns, failing row creation and leaving an orphan | Mime/size are now derived from the stored file and validated against the allowed MIME list; the new `Media` row is inserted inside try/catch that deletes the staged local file on failure (and on metadata failure). |
| 4 | Important | DB-failure compensation `$publicDisk->delete()` result ignored with `throw=false` | Compensation now checks the boolean/`exists()` after delete and logs `Log::error(...)` on failure while preserving and rethrowing the original DB exception. |
| 5 | Minor | Initial non-cover save before the cover lifecycle could leak a newly staged cover | `EditEvent::handleRecordUpdate` wraps the initial `fill()->save()` in try/catch; on failure it deletes the newly staged cover (only when it differs from the current one) and preserves the previous cover. |
| 6 | Minor | Post-commit local delete result ignored | The post-commit delete now verifies the file is gone (or logs a precise `Log::error`) without ever reverting the public DB state or removing the public file. |

### 7.2 RED (before fixes)

Command: `vendor/bin/phpunit tests/Feature/FilamentPublicationWorkflowTest.php`

```
tests 33 failures 5 errors 2
```

The 11 new tests were RED: tampering tests errored (no prefix guard), the
disk/prefix/MIME/republish tests failed (`Expected ... to be refused`), and
`test_media_upload_deletes_the_staged_file_when_the_row_creation_fails` failed on
orphaned staged files.

### 7.3 GREEN (after fixes)

```
{"tool":"phpunit","result":"passed","tests":34,"passed":34,"assertions":207}
```

Focused suite: **34 tests, 34 passed, 207 assertions, 0 failures, 0 errors.**

Adjacent suites (schema, private media, publication workflow, Filament auth, unit
isolation, unit-scope authorization, membership scope):

```
{"tool":"phpunit","result":"passed","tests":65,"passed":65,"assertions":311}
```

Pint: `{"tool":"pint","result":"fixed"}` (formatting only) then re-ran green.

### 7.4 Cleanup-failure behavior

- **Copy failure, compensation delete also fails:** original copy exception is rethrown;
  `Log::error('Berkas publik masih ada setelah kegagalan salin.')` is emitted; the local
  file is untouched and the record stays `disk=local, is_public=false`.
- **DB update failure, compensation delete also fails:** original DB exception is rethrown
  (never swallowed); `Log::error('Gagal membersihkan berkas publik setelah kegagalan basis
  data.')` is emitted; the local file remains.
- **Post-commit local delete fails:** public DB/file state stands (never reverted);
  `Log::error('Berkas privat media masih ada setelah penerbitan.')` is emitted.
- All three paths verified with a thin `FilesystemAdapter` wrapper whose `delete()`/`put()`
  results are controlled, and with `Log::shouldReceive('error')` message-matched
  expectations.

### 7.5 Anti-vacuous evidence (fix round)

| Removed guard | Failing test(s) |
|---|---|
| Service disk/prefix guard | `test_publish_media_refuses_a_media_record_backed_by_a_non_local_disk`, `test_publish_media_refuses_a_local_path_outside_the_gallery_media_prefix` (2/2) |
| Service content-MIME guard | `test_publish_media_refuses_a_local_file_whose_content_mime_is_not_allowed` (1/1) |
| Event cover tamper rule + `preventFilePathTampering` | `test_event_cover_upload_refuses_a_tampered_client_supplied_local_path` (1/1) |
| Gallery upload staged-file cleanup on row-create failure | `test_media_upload_deletes_the_staged_file_when_the_row_creation_fails` (1/1) |
| Event staged-cover cleanup on initial-save failure | `test_event_page_deletes_a_newly_staged_cover_when_the_record_update_fails` (1/1) |
| All three cleanup-failure `Log::error` calls | `test_publish_media_reports_a_failed_local_cleanup_...`, `..._failed_compensation_delete_...`, `..._failed_copy_compensation_...` (3/3 errors) |

### 7.6 Notes

- The disk/prefix tests would previously have passed for the wrong reason (the service
  hardcoded `local` as the source, so a public-backed record failed with "file not found");
  the non-local test now mirrors the file onto the local disk so **only** the disk guard
  can refuse it, making the assertion meaningful.
- The staged-file cleanup and event cover-lifecycle tests that need a *replacement* file
  invoke the real code path directly (`handleRecordUpdate` / `AturSampulEvent`) because the
  Livewire `FileUpload` harness cannot replace an existing keyed stored value (see §6.1).
- `mime_type`/`size` remain NOT NULL; metadata is now validated before insert and the
  staged file is removed if the probe or insert fails.
