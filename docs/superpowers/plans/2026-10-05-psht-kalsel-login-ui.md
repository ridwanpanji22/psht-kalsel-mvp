# Login UI Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refresh `/admin/login` to responsive desktop B/mobile C dark editorial PSHT styling without changing authentication behavior.

**Architecture:** Keep the existing Filament `Login` Livewire page, Firebase JavaScript contract, and one-root Blade view. Update only login wrapper markup/presentation; scope login-specific visual rules to its wrapper, reuse existing Tailwind/theme tokens and Vite-delivered Instrument Sans. Keep Google account-link recovery in flow and legible with its amber state.

**Tech Stack:** Laravel 13, Filament 5, Livewire, Blade, Tailwind CSS 4, Vite, Firebase Web SDK, PHPUnit, Chromium.

## Global Constraints

- Scope only `/admin/login`; `/admin/activate`, other Filament pages, and public portal remain visually unchanged.
- Preserve Firebase email/password, Google Sign-In, account-exists recovery/linking, ID token session exchange, CSRF, throttling, onboarding, and authorization behavior.
- Preserve every required DOM hook from `docs/superpowers/specs/2026-10-05-psht-kalsel-login-ui-design.md` §3.
- Keep one Livewire root and no nested document tags or duplicate title.
- Desktop B at `≥768px`; mobile C below 768px; 320px viewport must not overflow horizontally.
- Use current palette tokens `#0d1117`, `#0b0f16`, `#111722`, `#0720b0`, `#2162ed`, `#fbbf24`; Instrument Sans; no dependency, external font, stock photo, or new logo.
- Touch targets ≥44×44px, visible labels, keyboard-visible focus, WCAG AA contrast.

---

## File Map

- Modify `resources/views/auth/login.blade.php`: responsive desktop split + mobile single-card composition, retaining auth hook IDs/markup and Filament hooks.
- Modify `resources/css/app.css`: login-wrapper-scoped responsive background/card/focus/recovery rules only; public and other Filament/auth styles untouched.
- Modify `tests/Feature/AuthViewsRenderTest.php`: meaningful rendered contract assertions for unchanged Firebase hooks, one-root document, and scoped responsive markers/classes.
- No changes to `resources/js/firebase-auth.js`, routes, controllers, Firebase configuration, `/admin/activate`, or backend auth.

### Task 1: Pin Login Contract and Responsive Markers With Regression Tests

**Files:**
- Modify: `tests/Feature/AuthViewsRenderTest.php`

**Interfaces:**
- Consumes: current `/admin/login` response rendered through Filament simple layout.
- Produces: regression checks proving DOM hook contract, one Livewire document/root, responsive desktop/mobile wrappers, and absence of activation onboarding hooks on the login page.

- [ ] **Step 1: Add tests for required Firebase and recovery hooks.** Assert IDs/data attributes and `window._FIREBASE_CONFIG` remain rendered. Include all hooks listed in spec §3, without asserting incidental labels/class-string implementation.
- [ ] **Step 2: Add a behavior-level wrapper contract test.** Assert the login page has one marked root component, one H1, desktop and mobile layout markers/classes, responsive visibility switch at `md` if represented by clear semantic classes, and no full-document tags inside the component.
- [ ] **Step 3: Run `php artisan test --compact tests/Feature/AuthViewsRenderTest.php`.** Expected: new responsive marker assertions fail before implementation; existing Firebase/Livewire tests stay green.
- [ ] **Step 4: Keep tests on actual consumer-visible behavior.** Do not test CSS source strings, arbitrary class copies, or incidental defaults. Assert rendered layout regions, auth hooks, and single-document invariant.

### Task 2: Implement Responsive Dark Editorial Login Layout

**Files:**
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: stable DOM IDs, Firebase config, Filament view hooks, existing Vite bundle.
- Produces: desktop split identity/form layout; mobile compact dark card; same interactive DOM and behavior.

- [ ] **Step 1: Reshape login view only.** Keep exactly one component root. Add semantic desktop branding panel and mobile brand header; place the existing email/password form, Google button, status message, recovery section, and Filament auth render hook in the form panel. Retain every DOM ID/data hook and form field name unchanged.
- [ ] **Step 2: Apply responsive layout.** Desktop at `md` uses two balanced columns with left identity/message panel and right form card. Below `md`, hide desktop identity panel and render compact wordmark atop a full-width form card over dark background. Use safe-area-aware horizontal padding and flexible widths; no fixed viewport-height constraint that can clip mobile keyboard content.
- [ ] **Step 3: Scope styles to login wrapper.** Add a stable login-specific class/data selector; use existing dark/blue/amber palette. Avoid changing generic Filament or `body` selectors. Keep reduced-motion behavior minimal/no decorative motion.
- [ ] **Step 4: Style controls and recovery.** Inputs have dark field surface, visible borders and labels, legible placeholder/text contrast; primary action uses PSHT blue; Google action uses dark outlined surface; amber recovery panel is readable on dark background. Buttons remain ≥44px high. Preserve status/error `role` and live-region updates from existing JS.
- [ ] **Step 5: Run targeted render tests.** `php artisan test --compact tests/Feature/AuthViewsRenderTest.php`; expected: all hook/root/layout assertions pass.

### Task 3: Verify Auth Regression, Accessibility, Responsive Browser Matrix, and Commit

**Files:**
- Modify only if acceptance issue found: login Blade/CSS/tests listed above.

**Interfaces:**
- Consumes: Task 1+2 layout with unchanged Firebase client behavior.
- Produces: visually verified `/admin/login` at desktop/mobile sizes and clean regression suite/build.

- [ ] **Step 1: Run login-related feature tests.** `php artisan test --compact tests/Feature/AuthViewsRenderTest.php tests/Feature/FirebaseAuthEndpointsTest.php`.
- [ ] **Step 2: Run actual Chromium browser smoke on `/admin/login` at 320, 390, 768, 1024, 1440px.** Verify B layout on widths ≥768; C layout below 768; zero horizontal overflow; one H1; email/password/login/Google/recovery visible and operable; no JS console errors caused by markup change. Firebase live provider sign-in is separate and not claimed unless configured/tested.
- [ ] **Step 3: Verify keyboard and visual accessibility.** Tab through email → password → login → Google; focus indicator remains visible; test recovery form keyboard path; confirm labels, live region, and amber error surface remain legible. Run axe or equivalent accessibility check if available; remediate app-owned violations.
- [ ] **Step 4: Run complete gates.** `php artisan test --compact`, `vendor/bin/pint --dirty --format agent`, `npm run build`, `git diff --check`, and repository secret scan. Report only observed outcomes; explicitly separate Firebase provider smoke availability.
- [ ] **Step 5: Review complete diff for scope.** Confirm no edits to `firebase-auth.js`, activation page, routes/controllers, token exchange, or non-login styles. Do not commit the temporary visual-companion `.superpowers/brainstorm/` files. Commit as `feat(login): refresh responsive operator login UI`.

---

## Self-Review

- Spec coverage: B desktop, C mobile, dark editorial palette, unchanged auth contract/hooks, recovery/error state, responsive/no-overflow, accessibility, `/admin/activate` exclusion, and required verification all map to tasks 1–3.
- Placeholder scan: no TBD/TODO placeholders or underspecified implementation actions.
- Type/interface consistency: existing selectors and auth IDs stay verbatim; no new JavaScript API or auth endpoint is introduced.
- No independent subsystem decomposition needed; one view and scoped CSS change.
