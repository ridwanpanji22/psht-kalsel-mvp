# Repository Guidelines

## Project Overview

Laravel 13 modular monolith for PSHT Kalimantan Selatan: an internal operator panel (Filament 5 at `/admin`) plus a server-rendered public Blade portal (`/`, `/struktur`, `/profil/{slug}`, `/galeri`, `/kegiatan`, `/tempat-latihan`). Operator identity comes from Firebase Authentication; students/warga have no accounts. Indonesian UI (`APP_LOCALE=id`, `APP_TIMEZONE=Asia/Jakarta`).

## Architecture & Data Flow

- Single Laravel app: Eloquent domain models + Actions + Policies, Filament admin panel, public Blade controllers. Entry: `public/index.php` → `bootstrap/app.php` (routes `routes/web.php`, console `routes/console.php`, health `/up`) → providers in `bootstrap/providers.php`.
- Public flow: invokable/resource controllers in `app/Http/Controllers/Public/` query published models and return `public.*` Blade views (e.g. `HomeController.php`, `GalleryController.php`, `TrainingLocationController.php`). Public endpoints select only published content and approved fields.
- Operator auth flow: browser signs in via Firebase Web SDK (`resources/js/firebase-auth.js`), POSTs the ID token to `POST /admin/auth/session` (throttled `5,1`), Laravel verifies it server-side (`app/Http/Controllers/Auth/FirebaseAuthSessionController.php`), matches the local user by `firebase_uid`, checks `onboarded_at`/`is_active`, then `Auth::login` + session regenerate. First activation uses Firebase email-link invite → `/admin/activate` → `POST /admin/auth/activate` (`FirebaseAuthActivationController.php`), which fills `name` and activates user + assignments.
- Authorization: explicit Laravel policies (`app/Policies/`), Filament strict authorization (`app/Providers/Filament/AdminPanelProvider.php`), unit scope derived from active assignments (`app/Support/OrganizationScope.php`). Missing assignments deny panel entry. Domain hierarchy: Provinsi → Cabang → Ranting → Rayon; UKM/Komisariat are direct Cabang children with no children of their own, and the optional `supervising_branch_id` relation is separate mentoring metadata that grants no formal scope; pembinaan grants no implicit access.
- Publication workflow: shared `HasPublicationWorkflow` trait + `PublishContent` action; draft → pending review → published with audit events, publisher metadata, no self-approval.

## Key Directories

- `app/Models/`, `app/Actions/`, `app/Policies/`, `app/Support/` — domain logic, authorization, scope helpers.
- `app/Filament/Resources/`, `app/Filament/Auth/Pages/`, `app/Filament/Widgets/` — operator panel resources, custom Firebase login page, dashboard widgets.
- `app/Http/Controllers/Public/`, `app/Http/Controllers/Auth/` — public portal and Firebase session/activation controllers.
- `app/Console/Commands/` — `BootstrapProvinceOperator.php` (`operators:bootstrap-province {email}`), `MigrateOperatorToFirebase.php` (`operators:migrate-firebase {email}`).
- `app/Contracts/FirebaseAuthentication.php`, `app/Services/FirebaseService.php` — Firebase boundary (bound in `AppServiceProvider`); mock this contract in tests, never real credentials.
- `resources/views/public/`, `resources/views/auth/` — portal layouts/pages, login + activation views.
- `resources/js/` (`app.js`, `firebase-auth.js`), `resources/css/app.css` — Vite entries; Tailwind 4 + Leaflet + Bunny `Instrument Sans`.
- `database/migrations/`, `database/factories/` — hierarchy, membership, publication, media, and Firebase identity schema; test factories with named states.
- `../docs/product/`, `../docs/superpowers/specs/`, `../docs/superpowers/plans/` — PRD/MVP, approved design specs, implementation plans stored outside the application Git root. Specs carry approval status in their headers; check before building.
- `preview/` — static HTML/JS design previews with `node --test preview/check-preview.mjs`; preview-only, never production code.

## Development Commands

```bash
composer install && npm install && npm run build
php artisan migrate            # MySQL 8 for app data; SQLite only for isolated local work
composer run dev               # local server (or: npm run dev / npm run build for assets)
php artisan test --compact     # full suite (in-memory SQLite)
php artisan test --compact tests/Feature/FirebaseAuthEndpointsTest.php  # narrowest passing scope
vendor/bin/pint --dirty --format agent   # required after touching PHP
npm run build                  # required after touching frontend
composer validate && composer audit
git diff --check
```

Operator provisioning (Gmail only, invite-based; never Tinker):

```bash
php artisan operators:bootstrap-province admin@gmail.com
php artisan operators:migrate-firebase operator@gmail.com
```

## Code Conventions & Common Patterns

- Follow sibling files; don't create new top-level directories or add dependencies without approval. Use `php artisan make:` generators (`make:test --phpunit {Name}`, no suite directory in name).
- PHP 8.3, explicit return types and parameter type hints, constructor property promotion, curly braces always, TitleCase enum keys, PHPDoc shapes over inline comments. UI copy in Indonesian.
- Controllers are thin and single-purpose (many invokable); domain transitions live in `app/Actions/`; authorization in policies + `OrganizationScope`, never client-side. Prefer named routes + `route()` for links.
- Frontend: Tailwind utilities in Blade plus scoped custom classes in `app.css` (e.g. `.psht-*`, `.login-shell`); no raw hex in components — use existing theme tokens (`#0d1117`, `#0b0f16`, `#111722`, `#0720b0`, `#2162ed`, `#fbbf24`). Keep DOM hooks consumed by JS/tests verbatim (e.g. `data-firebase-login`, `login-form`, `google-login-button`).
- Error handling: server-side validation, throttled auth endpoints, JSON `error` payloads from auth controllers, Indonesian user-facing messages via `firebase-auth.js`. Never expose stack traces/PII in production responses.

## Important Files

- `routes/web.php` — all public/auth route names and middleware.
- `bootstrap/app.php`, `bootstrap/providers.php` — app wiring.
- `app/Providers/Filament/AdminPanelProvider.php` — panel id/path `admin`, strict auth, middleware.
- `app/Providers/AppServiceProvider.php` — Firebase binding, policy map, login render hook.
- `app/Models/User.php` — panel eligibility: active + onboarded + active assignment.
- `config/services.php`, `.env.example` — Firebase server path (`FIREBASE_CREDENTIALS`, outside repo, never committed) + six `VITE_FIREBASE_*` client vars (public, baked in at build).
- `config/auth.php` — single web session guard, no token guards. `config/queue.php` — database driver default; scheduled work via cron, no permanent workers.
- `public/.htaccess` — Apache rewrite for shared-hosting deploys.

## Runtime/Tooling Preferences

- PHP `^8.3` + Composer; Node 20.19+/22.12+ + npm (`package-lock.json`, `.npmrc` sets `ignore-scripts=true`). Never Bun/Yarn/pnpm; no Docker/CI manifests in repo.
- Locked toolchain: Vite 8.3.1, Tailwind 4.3.3, `laravel-vite-plugin` 3.1; runtime JS deps `firebase` 12.19 and `leaflet` 1.9.4.
- Data: MySQL 8 for app data; SQLite file locally, `:memory:` in tests (`phpunit.xml`).
- Target shared hosting: Apache-compatible, cron-based scheduling, log mailer default, database session/cache/queue drivers. Production needs HTTPS, cron evidence, encrypted off-host backup + restore drill before pilot.

## Testing & QA

- PHPUnit 12.5 + Mockery + Laravel HTTP/DB/Livewire assertions; Pint for style. Suites: `tests/Feature/` (flat, grouped by workflow: portal, auth, publication, authorization, commands) and `tests/Unit/` (`FirebaseServiceTest.php` only).
- Test style: `{Class}Test.php`, `test_snake_case(): void`, observable outcomes; `LazilyRefreshDatabase` for DB tests; `Storage::fake()` per test; Firebase via contract mocks; assert status + persisted state + side effects, and no unintended writes on failure. Factories `create()` persisted records; behavior reachable via HTTP belongs in Feature tests. Firebase live credentials/provider flow is explicitly outside automated proof.
- QA gates before merge: full suite + Pint + `npm run build` + `git diff --check` + secret scan; Chromium smoke on desktop + mobile viewports (layout, keyboard/focus order, no horizontal overflow, no console errors); Lighthouse Accessibility/Best Practices; report provider smoke separately if Firebase config is absent.
