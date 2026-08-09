# Browser E2E (Playwright)

Real browser evidence, not documentation. Every scenario in `testing/results/E2E-SCENARIOS.md`
was previously `NOT_RUN_BROWSER`; this directory is where converted scenarios actually execute.

## What's covered today (2026-08-09)

`critical-auth-and-rbac.spec.js` — ties to E2E-03 (authentication) and E2E-04 (scoped direct-route
denial):

- An authenticated System Administrator reaches `/dashboard` with zero console/page errors.
- An unauthenticated visitor hitting a protected route is redirected to `/login`.
- A wrong password is rejected and the user stays on `/login`.
- A store-scoped Cashier reaches `/pos` (200) but a direct URL to `/admin/settings` is denied
  server-side (403) — not just a hidden link.

`critical-rbac-matrix.spec.js` — extends E2E-04 to the full canonical-role direct-URL matrix
across POS, Inventory, Purchasing, Settings, and Audit, in a real browser:

- Six roles (System Administrator, Branch Manager, Cashier, Warehouse Manager, Accountant/Reviewer,
  and a role-less "no access" user) each attempt every one of the five route categories. Each
  "allowed" case asserts a real `200`; each "denied" case asserts a real server-side `403` — the
  same grants `RolePermissionScopeTest::test_each_role_reaches_only_its_authorized_routes` proves
  at the HTTP-test level, now proven in a real browser and extended to Purchasing/Inventory, which
  that Feature-level matrix does not cover.
- A forged direct POST to a real, existing `InventoryAdjustment`'s approval endpoint, sent by an
  authenticated Cashier with a valid CSRF token (so the `can:inventory_stock_card.approve` gate —
  not CSRF or a missing record — is unambiguously what rejects it), asserts a `403` and was
  independently confirmed via direct database inspection to have posted zero `StockMovement` rows
  and left the adjustment `submitted`, not `approved`.

33 tests total (4 + 29), all passing, executed against a real Chromium browser and a real running
`php artisan serve` instance.

## What's NOT covered yet

The remaining scenarios in `E2E-SCENARIOS.md` (catalog CRUD, full pricing-approval and POS-checkout
UI flows, inventory transfer/count UI flows, RTL/LTR, accessibility, cross-browser, mobile
viewports, etc.) are still `NOT_RUN_BROWSER` or `NOT_IMPLEMENTED`. Cross-store/cross-branch IDOR and
forged-out-of-scope-ID denial for Purchasing/Retail print routes has real coverage, but at the
backend HTTP-test level (`tests/Feature/Security/CrossStoreIdorTest.php`) rather than through a
browser — that same server-side code path is what a browser request would hit, but browser-level
IDOR evidence specifically is still open follow-up work. Converting the rest is real,
scenario-by-scenario follow-up work — each needs its own seeded fixture and page interactions, not
a shortcut. Do not report them as PASS.

## Running the suite

The suite needs a running app server pointed at a **dedicated, disposable** MySQL/MariaDB database — never the
developer's real local `toyjoy_local` schema, and never a staging/production URL. Create the schema in XAMPP/phpMyAdmin first.

```bash
# 1. Migrate + seed the dedicated MySQL/MariaDB schema
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=toyjoy_playwright_20260809 php artisan migrate --force
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=toyjoy_playwright_20260809 php artisan db:seed --force

# 2. Create known-password login fixtures (CanonicalAuthorizationSeeder's `local`-gated
#    demo users have random unknowable passwords — see DEC-064-era notes — so this suite
#    creates its own fixed-password users instead of relying on them)
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=toyjoy_playwright_20260809 php artisan tinker --execute="
  \$u = App\Models\User::query()->updateOrCreate(['username' => 'playwright-admin'], [
      'name' => 'Playwright Admin', 'email' => 'playwright-admin@toyjoy.local',
      'email_verified_at' => now(), 'password' => Illuminate\Support\Facades\Hash::make('PlaywrightTest!2026'),
      'is_super_admin' => true,
  ]);
  \$u->roles()->sync([App\Modules\Platform\Models\Role::query()->where('code','system-administrator')->value('id')]);
"
# (repeat with a cashier role + branch/store scope for 'playwright-cashier'; `critical-rbac-matrix.spec.js`
#  additionally needs 'playwright-branch-manager', 'playwright-warehouse-manager', 'playwright-reviewer',
#  and role-less 'playwright-no-access' — see the spec file for the exact role codes, and note its
#  "forged direct requests" test also expects a real, `submitted`-status InventoryAdjustment with
#  adjustment_number 'PWFORGE-ADJ-1' and id 1 to exist, so the RBAC gate — not route-model-binding's
#  404-for-a-nonexistent-id — is what the assertion proves)

# 3. Serve the app against that same database
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 DB_DATABASE=toyjoy_playwright_20260809 php artisan serve --port=8791

# 4. Run the suite
PLAYWRIGHT_BASE_URL=http://127.0.0.1:8791 npx playwright test
```

`npm run test:e2e` (see `package.json`) runs step 4 with the default `playwright.config.js`
`baseURL`; steps 1-3 are not automated into a single script yet — that's part of the follow-up
work, likely as an Artisan command or a `docker compose`-based fixture once one exists.

## Design notes

- Chromium only for the routine suite, per the QA audit's "broad Chromium coverage plus a smaller
  critical cross-browser suite" guidance. Add Firefox/WebKit projects to `playwright.config.js`
  once this suite is stable and a cross-browser subset is chosen deliberately, not by default.
- Semantic locators only (`getByLabel`, `getByRole`) — no CSS/XPath selectors. Watch for Flux UI's
  password-visibility-toggle button, whose `aria-label` ("Toggle password visibility") contains
  "password" as a substring and will collide with a loose `getByLabel('Password')` query; use
  `{ exact: true }`.
- `testing/helpers/auth.js` holds the shared `login()` helper — add further shared flows there
  instead of duplicating form interactions per spec.
- Forged direct requests via `page.request.post(...)` need the `XSRF-TOKEN` cookie (set
  automatically by Laravel's `VerifyCsrfToken` middleware after any `page.goto()`) echoed back as
  an `X-XSRF-TOKEN` header, or Laravel rejects the request with `419` before it ever reaches the
  route/gate — a `419` proves CSRF protection works, not that the RBAC gate does; read the cookie
  via `page.context().cookies()` and decode it.
- Login is throttled server-side to 5 attempts/minute per username+IP (`app/Providers/FortifyServiceProvider.php`,
  `RateLimiter::for('login')`). A single full suite run stays well under that, but re-running the
  same spec file repeatedly in quick succession against the same disposable database can trip it —
  the symptom is `login()`'s `waitForURL` timing out on a page showing "Too many requests". Fix
  with `DB_CONNECTION=mysql DB_DATABASE=toyjoy_playwright_20260809 php artisan cache:clear` (the login throttle uses the `database`
  cache store by default) rather than waiting out the window.
