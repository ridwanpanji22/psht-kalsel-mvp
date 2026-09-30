# PSHT Kalsel — source code

Laravel 13 modular monolith for PSHT Kalimantan Selatan. Internal operator panel: Filament 5 at `/admin`. Public portal: Blade pages at `/`, `/struktur`, `/profil/{slug}`, `/galeri`, `/kegiatan`, and `/tempat-latihan`.

## Local setup

Requirements: PHP 8.3+, Composer, Node.js/npm, and MySQL 8 for configured application data. Laravel for Agents created this project with Boost, SQLite local defaults, and PHPUnit. Automated tests override database settings to use in-memory SQLite; no production database has been configured.

1. Copy `.env.example` to `.env` if needed and set `APP_KEY` with `php artisan key:generate`.
2. Set `APP_NAME`, `APP_URL`, `APP_LOCALE=id`, `APP_FALLBACK_LOCALE=id`, and `APP_TIMEZONE=Asia/Jakarta` as required.
3. Set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in your local `.env`.
4. Install dependencies/build assets if not already installed: `composer install && npm install && npm run build`.
5. Run `php artisan migrate` after MySQL is available.
6. Create the initial operator in Tinker or through your approved provisioning process; never expose public registration. Operator panel access requires an active user with an active unit assignment.
7. Start locally with `composer run dev` and open the URL printed by Laravel.

For isolated local work before MySQL exists, `.env` may use SQLite. Automated tests use `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` from `phpunit.xml`; tests do not read/write production DB.

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

Production/pilot remains blocked until all owner decisions above are recorded, the MySQL/shared-hosting environment passes the same migrations and browser smoke, a scheduled cron run is evidenced, and an encrypted off-host backup is restored into isolated staging. The application has no configured map tile provider, SMTP, production database, initial production operator, or backup destination.

## Laravel for Agents

Project was created using `laravel new source-code --database=sqlite --npm --boost --no-interaction`. `AGENTS.md`, `CLAUDE.md`, Boost MCP configuration and bundled Laravel skills are generated in this directory. Filament 5 was installed. `cloud skills:install` was attempted but GitHub returned HTTP 403 API rate limit; generated Boost agent guidance remains installed and usable.
