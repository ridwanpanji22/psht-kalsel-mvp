# Task 1 Report — Backward-Compatible Publication Schema and Contracts

**Plan:** `docs/superpowers/plans/2026-10-02-psht-kalsel-public-ui-schema.md`
**Brief:** `.superpowers/sdd/2026-10-02-psht-kalsel-public-ui-schema/task-1-brief.md`
**Date:** 2026-10-02
**Scope:** Schema/model/enum foundation only. No Task 2+ Filament edit/relation workflows.

---

## 1. Deliverables

| Artifact | Change |
|---|---|
| `database/migrations/2026_10_02_122128_add_public_ui_publication_fields.php` | Additive, reversible migration |
| `app/Enums/GalleryCategory.php` | New backed enum (`latihan`, `kegiatan`, `pembinaan`) with Indonesian labels |
| `app/Filament/Resources/Galleries/Schemas/CategorySchema.php` | Shared write-boundary field validating the enum |
| `app/Filament/Resources/Galleries/Schemas/GalleryForm.php` | Wires the shared category field (minimal) |
| `app/Models/Event.php` | `cover_path` / `cover_alt_text` fillable + string casts |
| `app/Models/Gallery.php` | `category` fillable + enum cast; `media()` ordered `sort_order, id` |
| `app/Models/Media.php` | `sort_order` fillable + integer cast; `is_public` boolean (pre-existing) |
| `database/factories/{Event,Gallery,Media}Factory.php` | New-field defaults |
| `tests/Feature/PublicUiPublicationSchemaTest.php` | 11 focused tests |

### Migration content (exactly the brief's allowlist)

- `events.cover_path` — nullable string
- `events.cover_alt_text` — nullable string
- `galleries.category` — nullable string
- `media.sort_order` — unsigned integer, default `0`
- index `media (gallery_id, is_public, sort_order, id)`
- index `galleries (status, category, published_at)`
- **No raw driver-specific check SQL** (portability rule honored — see §5).
- `down()` drops only the newly added indexes and columns.

---

## 2. TDD evidence

### RED (before implementation)

Command: `vendor/bin/phpunit tests/Feature/PublicUiPublicationSchemaTest.php`

```
result failed — tests 9, passed 3, failed 1, errors 5
FAIL  test_legacy_event_gallery_and_media_rows_load_with_null_and_default_values
      Failed asserting that null is identical to 0.
ERR   test_event_cover_fields_are_fillable_and_cast_as_nullable_strings
      SQLSTATE[HY000]: General error: 1 table events has no column named cover_path
ERR   test_gallery_category_casts_to_enum_and_stays_null_for_legacy
      SQLSTATE[HY000]: General error: 1 table galleries has no column named category
ERR   test_media_sort_order_casts_to_integer_and_defaults_to_zero
      SQLSTATE[HY000]: General error: 1 table media has no column named sort_order
ERR   test_gallery_media_relation_is_ordered_by_sort_order_then_id
      SQLSTATE[HY000]: General error: 1 table media has no column named sort_order
ERR   test_migration_up_then_down_preserves_existing_publication_rows
      require(...add_public_ui_publication_fields.php): Failed to open stream
```

The 3 passing tests were the enum value/label unit test (the enum was created first because
`Rule::enum(GalleryCategory::class)` cannot reference a non-existent class) and the validator
parity tests that already accepted null. All schema-dependent behavior was RED as expected.

### GREEN (after implementation)

Command: `php artisan test --compact tests/Feature/PublicUiPublicationSchemaTest.php`

```
{"tool":"phpunit","result":"passed","tests":11,"passed":11,"assertions":64,"duration_ms":874}
```

Focused suite: **11 tests, 11 passed, 64 assertions, 0 failures, 0 errors.**

Adjacent regression check (existing suites touching these models):

```
PublicPortalTest + PrivateMediaAccessTest + PublicationWorkflowTest
result passed — tests 23, passed 23, assertions 95
```

---

## 3. Migration up/down evidence (real SQLite file DB)

`DB_CONNECTION=sqlite DB_DATABASE=/tmp/schema_verify.sqlite php artisan migrate --force`

```
2026_10_02_122128_add_public_ui_publication_fields .. 3.24ms DONE
```

Post-`up()` schema (PRAGMA inspection):

```
events.cover_path      varchar notnull=0 default=NULL
events.cover_alt_text  varchar notnull=0 default=NULL
galleries.category     varchar notnull=0 default=NULL
media.sort_order       INTEGER notnull=1 default='0'
galleries_status_category_published_at_index         unique=0
media_gallery_id_is_public_sort_order_id_index       unique=0
```

`php artisan migrate:rollback --step=1` removed **only** the new columns and indexes; all
pre-existing publication columns (`slug`, `title`, `status`, `published_at`, `alt_text`,
`is_public`) survived. Re-running `php artisan migrate` re-applied cleanly (idempotent cycle).

Data preservation is also asserted in-test: legacy rows inserted with raw `DB::table(...)`
(no new columns) still load through Eloquent with `cover_path=null`, `cover_alt_text=null`,
`category=null`, `sort_order=0`, `is_public=false` after migration up→down→up.

---

## 4. Test coverage map (brief → test)

| Brief requirement | Test |
|---|---|
| Nullable existing record compatibility | `test_legacy_event_gallery_and_media_rows_load_with_null_and_default_values` |
| Event cover fillable + nullable cast | `test_event_cover_fields_are_fillable_and_cast_as_nullable_strings` |
| Enum casting + legacy null | `test_gallery_category_casts_to_enum_and_stays_null_for_legacy` |
| `sort_order` integer cast + default 0 | `test_media_sort_order_casts_to_integer_and_defaults_to_zero` |
| Write-boundary validation (validator parity) | `test_write_boundary_rejects_category_outside_the_fixed_enum`, `test_write_boundary_accepts_each_allowed_category_and_null` |
| Real Filament write-boundary validation | `test_gallery_filament_create_rejects_category_outside_the_fixed_enum`, `test_gallery_filament_create_persists_each_allowed_category` |
| Enum values + Indonesian labels | `test_enum_exposes_the_three_fixed_values_with_indonesian_labels` |
| Ordered media default/tie behavior | `test_gallery_media_relation_is_ordered_by_sort_order_then_id` |
| Migration data preservation up/down | `test_migration_up_then_down_preserves_existing_publication_rows` |

Ordering test asserts `[sort_order 0] < [1, id a] < [1, id b] < [2]` for both
`$gallery->media()->pluck('id')` and `$gallery->load('media')->media`, covering the
deterministic `id` tie-break for legacy rows that all default to `0`.

### Test sensitivity (anti-vacuous check)

The negative Filament test was proven non-vacuous: temporarily removing the enum options
from `CategorySchema` makes both `test_gallery_filament_create_*` tests error with
`Filament failed to validate the [data.category] field's selected options ...`. Restored
and re-verified green.

---

## 5. Portability rule — no raw check constraint

Per `docs/superpowers/specs/...-design.md` §3.2 and plan Global Constraints, the fixed
enum is enforced at the **application write boundary**, not by a DB check constraint, so the
same migration is valid on MySQL 8 and SQLite.

Implementation detail: `Filament\Forms\Components\Select` is configured with the backed enum
class-string (`->options(GalleryCategory::class)`) rather than a hand-built value→label map.
This keeps Filament on its enum-aware allowlist/validation path, so the allowed values are
enforced by the form's own rules at the real write boundary, and it keeps a single source of
truth for allowed values and labels (the `GalleryCategory` enum). The explicit
`Rule::enum(GalleryCategory::class)` rule mirrors that allowlist for non-Filament write paths.
Rejected alternatives and evidence are recorded in §7.

---

## 6. Self-review

- **Scope discipline:** only Task 1 files changed; no Event/Gallery edit pages, no media
  relation manager, no controllers/routes/views (Task 2+).
- **Portability:** migration uses portable schema builder only; no `DB::statement`, no
  driver-specific `CHECK`.
- **Backward compatibility:** all new columns nullable or defaulted; no backfill; no data
  mutation; `down()` limited to new indexes/columns.
- **Conventions followed:** `#[Fillable]` attribute, spread `publicationCasts()`, enum
  `label()` match style consistent with `PublicationStatus`, factories updated, Pint clean
  (`vendor/bin/pint --dirty` fixed only an unused import), tests use `LazilyRefreshDatabase`
  and `Livewire::test(...)` matching existing Filament test style.
- **Determinism:** media ordering explicitly orders by `sort_order` then `id`.

---

## 7. Concerns / deviations

1. **Filament Select enum configuration (resolved, but notable).** The category field uses
   Filament's enum-aware path (enum class-string), which is the idiomatic configuration and
   keeps one source of truth for allowed values/labels. Task 2's Gallery Media relation
   manager and any future category filters should reuse
   `App\Filament\Resources\Galleries\Schemas\CategorySchema::field()` rather than rebuilding
   an options/label array, so the allowlist and labels cannot drift.
2. **`media.sort_order` is `notnull` with default 0 (by design).** The brief specifies
   "unsigned default `0`"; SQLite reports `notnull=1` for a defaulted non-nullable column.
   Legacy rows receive `0` and the `id` tie-break preserves deterministic ordering. The
   design explicitly accepts duplicate positions after first reorder (§9 risk).
3. **No Event/Gallery Filament edit surfaces yet.** The brief's interface note
   ("Filament edit surfaces use these fields") is only satisfied for the Gallery category
   field within Task 1's minimal boundary; Event cover and Gallery media form surfaces are
   Task 2 by the plan's own split, so they were intentionally not built here.
4. **`GalleryCategory::options()` remains** as the shared value→label map for non-Filament
   consumers (e.g. Blade filters in Task 3); it is the same enum-derived allowlist used by the
   Filament field, so there is no divergent second list.
5. **Validator-parity tests** (`test_write_boundary_*`) mirror the shared `Rule::enum`
   rule rather than routing through a Task-1 application action, since no Task-1 write
   action exists yet; the real Livewire Filament tests provide the end-to-end coverage.

---

## 8. Commit

Base: `a6859df` (docs(public): keep category constraint portable)

```
c4c1b5a  feat(public): add backward-compatible publication schema and contracts
```

This report is committed as part of the task commit (the SHA above is the final HEAD
after this report was folded in; a report cannot embed its own not-yet-existing hash
exactly, so the commit subject is the stable identifier).

---

## 9. Fix round 1 — docs-only correction

**Reviewer finding (verbatim):** report lines 146–149 claim PHP coerces alphabetic keys
`latihan`, `kegiatan`, `pembinaan` to integers; PHP preserves these nonnumeric string keys.

**Verdict:** accepted. The claim was inaccurate. `latihan`, `kegiatan`, `pembinaan` are
non-numeric strings, and PHP preserves non-numeric string keys as strings (only
canonical numeric strings such as `"0"`–`"3"` are coerced to integers). The original text
conflated that with the earlier numeric-key observation, which is unrelated to these
alphabetic enum values.

**Correction:** §5 and §7 now state the actual rationale for using
`->options(GalleryCategory::class)`: it keeps Filament on its enum-aware
allowlist/validation path and maintains a single source of truth for allowed values and
labels. The irrelevant key-coercion claim was removed. No code was changed; no tests were
rerun (code is byte-identical to the tested commit).
