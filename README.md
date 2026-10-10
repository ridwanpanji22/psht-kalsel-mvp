# PSHT Kalsel — source code

Laravel 13 modular monolith for PSHT Kalimantan Selatan. Internal operator panel: Filament 5 at `/admin`. Public portal: Blade pages at `/`, `/struktur`, `/profil/{slug}`, `/galeri`, `/kegiatan`, and `/tempat-latihan`.

## Local setup

Requirements: PHP 8.3+, Composer, Node.js/npm, and MySQL 8 for configured application data. Laravel for Agents created this project with Boost, SQLite local defaults, and PHPUnit. Automated tests override database settings to use in-memory SQLite; no production database has been configured.

1. Copy `.env.example` to `.env` if needed and set `APP_KEY` with `php artisan key:generate`.
2. Set `APP_NAME`, `APP_URL`, `APP_LOCALE=id`, `APP_FALLBACK_LOCALE=id`, and `APP_TIMEZONE=Asia/Jakarta` as required.
3. Set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in your local `.env`.
4. Install dependencies/build assets if not already installed: `composer install && npm install && npm run build`.
5. Run `php artisan migrate` after MySQL is available.
6. **Configure Firebase Admin credentials.** Store the service account JSON **outside the repository** (e.g. `~/secrets/firebase-service-account.json` or a path under `storage/` that is never committed) and point `FIREBASE_CREDENTIALS` at that absolute path. Do not commit the key, and do not place it inside the project tree. Set `FIREBASE_PROJECT_ID` to the same project.
7. **Configure all Firebase Web (client) settings.** Fill every `VITE_FIREBASE_*` variable — `VITE_FIREBASE_API_KEY`, `VITE_FIREBASE_AUTH_DOMAIN`, `VITE_FIREBASE_PROJECT_ID`, `VITE_FIREBASE_STORAGE_BUCKET`, `VITE_FIREBASE_MESSAGING_SENDER_ID`, and `VITE_FIREBASE_APP_ID`. These are public values embedded in the login/activation pages and rebuild with `npm run build`. Missing values leave the operator login with “Konfigurasi Firebase belum tersedia”.
8. **Enable the Firebase providers.** In the Firebase Console enable the **Email Link (passwordless sign-in)** and **Google** sign-in providers, and add every host that serves `/admin/login` and `/admin/activate` to **Authentication → Settings → Authorized domains** (include `localhost` for local work). Email Link invites are sent by the Admin SDK in Indonesian (`locale = 'id'`).
9. **Create the initial operator** (not via Tinker) with the province bootstrap command:

   ```bash
   php artisan operators:bootstrap-province admin@gmail.com
   ```

   It provisions the first active Province operator and emails the activation invite.
10. **Migrate a legacy operator** (existing local account without a Firebase identity) with:

    ```bash
    php artisan operators:migrate-firebase operator@gmail.com
    ```

11. Start locally with `composer run dev` and open the URL printed by Laravel.

Panel access requires an active user with an active unit assignment **and** a completed onboarding (`onboarded_at`). Operator login itself remains blocked locally until the replacement service-account key and web configuration above are in place (see the note below).

For isolated local work before MySQL exists, `.env` may use SQLite. Automated tests use `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` from `phpunit.xml`; tests do not read/write production DB.

### Operator login/activation smoke is blocked until credentials are replaced

A previously exposed service-account key was revoked, and no replacement key or Firebase web configuration is present in this environment. Until a **new service-account key** (path configured outside the repo via `FIREBASE_CREDENTIALS`) and the complete `VITE_FIREBASE_*` web settings are supplied:

- the invite emails from the bootstrap/migration commands cannot be sent, and
- the end-to-end login/activation browser smoke cannot be completed.

This is an environment prerequisite, not a code defect. Tests mock the Firebase boundary and do not require real credentials.

## Demo dataset and local demo login

A deterministic demo dataset powers local presentations without any real identity or
Firebase credential. It is produced by `DatabaseSeeder` → `DemoDatabaseSeeder` and
refuses to run when `APP_ENV=production`.

Local runbook (on a disposable local database):

```bash
php artisan migrate          # create the schema on a clean local SQLite/MySQL database
php artisan db:seed          # seeds the full demo dataset (idempotent by natural key)
php artisan storage:link     # expose storage/app/public so public gallery media resolves
```

> **Reset warning — explicit and destructive.** Resetting is a deliberate, destructive
> step you run yourself; the seeders never do it for you. `DemoDatabaseSeeder` (and
> `DatabaseSeeder`) never run `migrate:fresh` and never delete rows. To re-seed from
> scratch, reset the demo database explicitly — either replace the local SQLite file or run
> `php artisan migrate:fresh` **only** on a disposable local database — then `php artisan
> db:seed` again. Reruns on an already-seeded database update records by natural key
> instead of duplicating them.

Demo operators (one per organization unit; 1 province + 13 branches + 65 rantings +
208 rayons + 13 UKM + 13 komisariat = **313 accounts**):

- **Email pattern:** `demo.provinsi@psht-kalsel.test`, `demo.cabang.01@psht-kalsel.test`
  (through `.13`), `demo.ranting.branch-01-01@psht-kalsel.test`, `demo.rayon.…`,
  `demo.special-ukm-branch-01@psht-kalsel.test`, and so on.
- **Shared password:** `PshtKalsel@1922` (local demo only — never use in production).
- Every account is active, onboarded, carries a unique dummy `firebase_uid`, and has an
  active assignment on its unit.

> **Do not reuse these credentials anywhere but local SQLite.** They are fixed, shared, and
> documented here on purpose; they are not secrets. Never point the demo seeder or demo
> login at a shared, staging, or production database, and never enable them outside
> `APP_ENV=local`/`testing`.

Because the dummy Firebase UIDs cannot authenticate against real Firebase, the server
exposes a guarded, password-based demo login for local presentation. It is **disabled by
default** and only honoured when both conditions hold:

```dotenv
APP_ENV=local            # or testing
DEMO_AUTH_ENABLED=true   # default is false
```

When enabled, a visibly labelled **Demo lokal** form is rendered under the Firebase form on
`/admin/login` and posts to `/admin/demo/auth/session`. When disabled, or in any
non-local/testing environment (including production), the endpoint returns `404` and the
form is not rendered. Firebase login and token verification are never modified; production
login stays Firebase-only. Failed demo logins return a uniform validation error so callers
cannot probe which accounts exist.

## Verification

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
npm run build
composer validate
composer audit
```

The application smoke path includes operator login and scoped resource access; organization and membership workflows; content review; public-only pages/media; and Leaflet rendering when a tile provider is configured.

### Local evidence

- Browser smoke on 2026-09-30: public home, hierarchy, training-location empty state, Filament login, dashboard, membership list, and membership create form rendered at `http://127.0.0.1:8010`; no console errors. Chrome recorded two Filament native form-label issues, but DOM inspection found no unresolved `label[for]` references; recheck after Filament upgrades.
- Synthetic SQLite benchmark on 2026-09-30: bulk-inserted 50,000 non-PII membership/person rows in 4,127.11 ms; scoped active-member pagination (50 rows) took 10.47 ms; unit/type/status summary took 3.72 ms; PHP peak memory 60.5 MiB. All synthetic rows were removed afterward. This confirms local query behavior only; it does not establish shared-hosting MySQL capacity or response targets.

## Current product boundary

Implemented MVP foundation: unit hierarchy and positions, scoped operator assignments/policies, student/warga profile lifecycle, status/approval/audit records, publication workflow, private gallery file handling, and server-rendered portal pages.

Not implemented in this MVP: correspondence/signature, finance ledgers/reports, training evaluation, and belt examination. These remain later PRD stages; they are not represented by empty or simulated screens.

## Owner decisions required before production data/pilot

- Approve the action/field matrix by role and unit, including who can create downstream operators, create/update members, submit/review/approve status transitions, and manage/publish content.
- Provide official member-number format/validation. Current implementation uses a globally unique opaque value, maximum 100 characters; this is provisional.
- Decide required member fields, duplicate-review workflow, membership transition set, data correction/deletion/retention policy, and privacy/legal basis.
- Decide allowed public fields, content reviewers, public media processing/moderation and operational content owner.
- Confirm publication rights and consent for the homepage hero video and its poster, including every identifiable person shown. Until confirmed in writing, the hero ships as poster-only with no video source or autoplay (see Release blockers).
- Choose map tile provider, acceptable usage policy, production attribution text, and tile service configuration. Leaflet frontend has no tile URL/provider configured; do not publish production locations on an unconfigured basemap.
- Verify actual shared-hosting PHP/extensions, MySQL, HTTPS, cron, SMTP, writable directories, resources, off-host encrypted backup, restore, operational owner, pilot cohort, SLA/RPO/RTO.

Until each decision is approved, its dependent path remains development-only and must not be used with production personal data. Current roles are intentionally conservative: an active operator can provision only another `operator` assignment in its formal subtree; operator assignments and status histories are read-only after creation; any richer action matrix must be approved and tested before production.

## Implementation decisions

- Explicit hierarchy: Provinsi → Cabang → Ranting → Rayon; UKM/Komisariat formal children of Provinsi, optional supervising Cabang relation. Pembinaan does not implicitly grant access.
- Unit scope resolves active assignment roots and formal descendants. Missing assignments deny panel entry. Resource policies use explicit Laravel policies and Filament strict authorization.
- Warga number is externally issued, input by operator, unique and required for warga. No generator or NIK field.
- Membership and publication changes preserve history/audit; routine delete actions are not provided.
- Public endpoints select only published content and approved fields. Private media is delivered only after unit authorization.
- `.env` and secrets must never be committed. No production credential/database has been entered by setup.

### Release blockers

Before production deployment the owner must explicitly confirm the publication rights for the homepage hero media and the consent of every identifiable person shown in it. The presence of an asset in the repository is not approval to publish it: an asset being committed, referenced, or rendered in a build proves nothing about rights or consent. Where consent is not yet recorded, the homepage hero must ship with the poster image fallback only, with no video source and no autoplay, until the owner signs off. Do not publish to production or claim release approval from asset presence alone.

Production/pilot remains blocked until all owner decisions above are recorded, the MySQL/shared-hosting environment passes the same migrations and browser smoke, a scheduled cron run is evidenced, and an encrypted off-host backup is restored into isolated staging. The application has no configured map tile provider, SMTP, production database, initial production operator, or backup destination.

## Laravel for Agents

Project was created using `laravel new source-code --database=sqlite --npm --boost --no-interaction`. `AGENTS.md`, `CLAUDE.md`, Boost MCP configuration and bundled Laravel skills are generated in this directory. Filament 5 was installed. `cloud skills:install` was attempted but GitHub returned HTTP 403 API rate limit; generated Boost agent guidance remains installed and usable.
