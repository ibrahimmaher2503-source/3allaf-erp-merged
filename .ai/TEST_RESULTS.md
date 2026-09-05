# Test and Verification Results

## 2026-09-05 — v0.1.22-hotfix14 focused verification

- Direct database-free arithmetic/source verification passed 20 assertions covering all required upward-derived-price examples, nearest-5 cash examples, unchanged base/manual price boundaries, exact non-cash settlement, append-only setting migration, and safe missing/invalid configuration handling.
- PHP 8.5 syntax passed for all 14 changed/new PHP files. The POS route subset, Arabic/English JSON parsing, and `git diff --check` passed.
- Per the standing current-task directive, no new automated test file was created and no PHPUnit suite was run. No database, migration, seeder, browser, Composer, Vite, production, backup, tag, push, or remote operation ran.

## 2026-09-05 — v0.1.22-hotfix13 focused verification

- Pure database-free PHPUnit regression: `tests/Feature/V0122Hotfix13CashDrawerIntegrityTest.php` passed 4 tests / 43 assertions covering retained valid shift opening, store-derived ownership and mismatch rejection, deterministic legacy backfill/fail-closed reporting/constraints, invalid-selector exclusion, and company/branch/store scope enforcement.
- The preserved Hotfix12 shift-route regression passed database-free with 1 test / 24 assertions using locale `zz` and a disposable process-only application key. The first command named the nonexistent `phpunit.xml`; the corrected configuration then reached 19 assertions before identifying the missing isolated application key. Neither setup failure accessed a database.
- Changed PHP syntax passed for the 10 final changed/new PHP files. After the test harness was converted to pure PHPUnit, that test file was linted again and passed.
- The exact `pos.shift` GET, `pos.shift.open` GET, and `pos.shift.store` POST route registrations passed a database-free router probe. Arabic and English JSON parsing and `git diff --check` passed.
- One initial combined attempt reported 5 errors / 0 assertions because the standard Laravel test/route harness tried to query `translation_overrides` through the deliberately unreachable MySQL endpoint. It did not connect to or mutate any database. The Hotfix13 suite was then made framework-boot-free; no application behavior changed for this harness correction.
- No migration, seeder, database, Composer, Vite, browser, broad test suite, production path, service, active symlink, tag, push, or remote operation ran.

## 2026-09-04 — v0.1.22-hotfix10 focused verification

- PHP 8.5.9 syntax passed for all five changed PHP/config/test files. Blade `view:cache` passed using the documented translation database-outage fallback, query observability disabled, an intentionally invalid database driver, task-local compiled-view output, and no `.env`.
- Two earlier fail-closed Blade attempts stopped before compilation: first at the query-observability listener and then at the translation override loader under `APP_ENV=testing`. Neither connected to a database or changed data.
- Arabic and English JSON parsing, focused source contracts, diff hygiene, absence of frontend-source changes, and the `storage/storage` guard passed.
- `public/build/manifest.json` SHA-256 is `b0d6e69cb067407c8ecc07e72eed30f06fb88a4050a3bd55f4f526c5c20606df`; all 12 Vite references are regular, non-symlink, non-empty files.
- `public/build/fonts-manifest.json` SHA-256 is `66edf17c93351fe01158be7414a441a762f106417c74351881876b405b8b10ca`; all 7 unique font-manifest and embedded CSS references are regular, non-symlink, non-empty files.
- No Vite build ran because application frontend sources and both dependency lockfiles are unchanged from the verified Hotfix9 source/build identity.
- Focused PHPUnit and Arabic desktop, English desktop, and Arabic mobile browser automation were requested but blocked by the execution policy before launch. No test/browser pass, console-error result, or overflow result is claimed.

## 2026-09-03 — v0.1.22-hotfix8 focused pass

- Hotfix7 regression contract passed 5 tests / 26 assertions after updating only its release-version assertion. A combined historical Hotfix4/Hotfix7 run passed 9/10; the sole failure is the documented obsolete Hotfix4 sidebar CSS source-string expectation.
- PHP syntax, Blade compilation, Arabic/English JSON, Vite 8.2.0 production build, diff hygiene, and authenticated Arabic/English desktop plus Arabic 390px balances checks passed. Browser console errors and horizontal overflow were zero.
- Disposable MariaDB `toyjoy_hotfix8_verify` applied migrations through 000106. No Hotfix8 migration or index was added.

## 2026-09-03 — v0.1.22-hotfix7 focused pass

- Hotfix7 PHPUnit passed 5 tests / 26 assertions. PHP syntax, Blade compilation, both locale JSON files, focused routes, diff hygiene and the Node 20 Vite production build passed.
- Fresh disposable MariaDB applied all migrations; migration `2026_09_03_000106_add_inventory_count_sessions` passed down/up, creating four session tables and nine permissions.
- Mandatory reconciliation passed: opening 10, counted 10, sale-after-cutoff -2, expected 8, normalized 8, variance 0; transfer lock rejected the counted location.
- Two parallel counters produced exactly two contributions totaling 2; request replay remained idempotent. Product-code and model searches used `products_item_code_unique` and `prod_model_status_idx` under EXPLAIN.
- Authenticated Arabic/English desktop and Arabic 390px checks covered balances, stores, count creation and active entry with zero console errors and zero horizontal overflow. Route query samples were balances 41, stores 93 on cold readiness-cache creation, count creation 41 and active counting 49; duplicates observed were shared session/translation/permission/layout loads, not unbounded product preload.
- A broader historical source-contract selection reported 26/33 passing: failures were obsolete Hotfix4/Hotfix6 version/CSS expectations and pre-existing missing M54 fixture/view contracts, not runtime failures. They are not represented as passing.

## 2026-09-03 — v0.1.22-hotfix6 focused pass

- Focused PHPUnit passed 4 tests / 34 assertions with zero errors or failures. Changed PHP syntax, Blade compilation, Arabic/English JSON parsing and diff hygiene passed.
- Authenticated isolated-browser verification passed Arabic desktop with all Basic Data groups expanded and the mobile drawer; 21 unique leaves appeared in category counts 5/5/11, exact setup links and active expansion passed, and no horizontal overflow or browser errors occurred.
- Arabic and English Audit Logs exposed no tested raw event keys, model class names or configuration key; the combined store create wording passed. Isolated MariaDB `rajeh_hotfix6_verify` reports migrations through 000105 Ran; Hotfix6 adds no migration.
- Shared-layout `/settings/profile` retained 5 queries and zero duplicates. Five-sample median was 140.345 ms versus Hotfix5 evidence 136.458 ms (+2.8%); app-only median improved from 130.258 to 124.455 ms. Node 20.20.0 Vite build passed after the desktop sidebar stylesheet correction; manifest SHA-256 is `baed65ad827593d44cbc8352a62c8807ea1a95b924945fbade1b68852302bb3c`.

## 2026-09-03 — v0.1.22-hotfix5 corrective no-regression pass

- Only Products, Pricing and Unpriced were re-benchmarked against exact Hotfix4 under identical isolated conditions. All three corrected medians, p95 values, query counts and SQL medians improved; see `.ai/evidence/v0.1.22-hotfix5/CORRECTIVE.md`.
- Affected PHPUnit passed 9 tests / 88 assertions with zero errors, failures or skips after explicitly supplying the translator dependency in the readiness test harness.
- Four affected PHP/migration files passed syntax. Migrations 000104/000105 report Ran, the harmful product index is absent, and diff checks passed. No broad, visual or PDF checks were repeated.

## 2026-09-02 — v0.1.22-hotfix5 performance

- Exact-baseline/candidate benchmark covered 20 authenticated routes with one warm-up and five samples each on the same isolated MariaDB fixture; full results and method are in `.ai/evidence/v0.1.22-hotfix5/PERFORMANCE.md`.
- Focused regression passed 5 tests / 55 assertions. The separate legacy readiness unit file has one pre-existing translator-bootstrap error and 3 passing tests / 25 assertions; no broad suite ran.
- Changed PHP syntax, Blade/config/route/event cache compilation, Arabic/English JSON, reversible migration up/down/up, MariaDB query plans, 20-route Arabic desktop journey, POS search, mobile navigation, and diff checks passed.
- Browser result: 20/20 HTTP 200, 20/20 RTL, zero console/page errors, zero overflow; mobile drawer opened without overflow.

## 2026-08-27 — M50A.1 focused local visual verification

- Owner-authorized local Playwright rendering only; no automated application test suite was run.
- Ten evidence cases: Arabic/English 1440px dashboard, 390px/360px mobile dashboard, Arabic mobile drawer, English collapsed sidebar tooltip, Arabic Inventory module, English Sales & POS navigation, 768px tablet dashboard, and 1024px header quick-create state.
- Result: zero serious/critical Axe violations, zero horizontal document overflow, zero external requests, one footer per case, and no unlabeled icon-only controls.
- Cairo proof for all Arabic cases: `lang=ar`, `dir=rtl`, local Arabic and Latin WOFF2 responses HTTP 200, `document.fonts.check()` true for 400/600/700, Cairo-first computed styles on body/sidebar/header/KPI/footer, and Cairo Arabic canvas metrics distinct from Arial fallback.
- Evidence: `/home/rajeh/worktrees/toyjoy-release-artifacts/m50a1-evidence-20260827/verification.json` and the ten PNG captures in the same directory.

## 2026-08-30 — M52 focused verification

- Owner-authorized focused PHPUnit passed 11 tests and 33 assertions. No broad suite ran.
- Isolated task-local MariaDB migration 000097 completed in batch 2 and created all three Opening Inventory tables.
- Genuine authenticated evidence: 10 Arabic/English desktop/mobile cases passed with zero serious/critical Axe violations, zero document overflow, no console errors or external requests, and local Cairo loaded.
- Arabic product, category, and brand PDFs embedded subsetted Cairo-Regular only; no DejaVu reference was present, and all three rendered pages were captured as PNG.

## 2026-08-31 — M53 focused verification

- Owner-authorized focused PHPUnit passed 6 tests and 28 assertions for formula/rounding, protected List 0, dated overrides/removal, outlet resolution, snapshot integrity, authorization, and Setup reasons. No unrelated suite ran.
- Isolated task-local MariaDB migration 000098 completed successfully and created the override table, list metadata, assignment foreign keys, and permissions.
- Genuine authenticated evidence covered Pricing Lists in Arabic desktop and English mobile, Arabic Product Card pricing matrix, English outlet assignment, and Arabic mobile Setup readiness. All returned HTTP 200 with correct direction, local Cairo on Arabic pages, zero serious/critical Axe violations, overflow, console errors, or external requests.
# 2026-08-31 — M54 focused verification

- Focused PHPUnit: 5 tests / 29 assertions passed.
- Covered posting transaction/idempotency, exact-distribution enforcement, price resolution, permissions, and two-stage/label surfaces.
- Migration `000099` passed on disposable MariaDB `rajeh_m54_20260831`; no shared database was used.
- No broad automated suite was run.

# 2026-08-31 — v0.1.21 Final Acceptance

- Focused PHPUnit passed 12 tests / 95 assertions after correcting the obsolete icon expectation exposed by the first focused run.
- Isolated MariaDB `rajeh_v021_uat_20260831`: initial seed created 44 marked records; repeat seed reused the same batch; dry-run purge reported those 44 records; confirmed purge removed them; reseed created a new 44-record batch.
- Golden Path integrity: exactly one Draft, Awaiting Distribution, Approved, and Reversed invoice; receiving remainder `0.000000`; two destination price snapshots.
- The journey exposed and verified a fix for stale pending approval source versions after distribution.
- Node 20.20.2 Vite build passed; manifest SHA-256 `4d41c4fce936e8ba68f79ec69c89c36178f4481a8fa660774cce61de4894fe3f`. The host-default Node 16 attempt failed before compilation and changed no application source.

# 2026-08-31 — v0.1.21 Purchasing Localization/Layout Hotfix

- Directly related tests: initial pass 15/16; corrected the sole over-broad test assertion, then the hotfix file passed 4 tests / 249 assertions.
- Eight task-local visual cases covered list/create, Arabic/English, and 1440px/390px. All passed mixed-language, raw-enum, clipping, overlap, and document-overflow checks; representative captures were visually inspected.
- One Node 20.20.2 build passed. No database, broad suite, PDF, Artisan, production, or remote operation ran.

# 2026-08-31 — v0.1.21 Purchasing Search/Barcode Hotfix 2

- Directly related test pass completed 32/33; corrected its sole outdated search-contract assertion, then the affected file passed 4 tests / 33 assertions.
- Six task-local bilingual visual cases covered Purchase Invoice create and Product Card create/edit. All passed mixed-language, exposed-variable, and horizontal-overflow checks; representative Arabic Product Card edit and English Purchase Invoice create captures were visually inspected.
- No database cycle, broad regression, PDF, Artisan, migration, UAT execution, production, or remote operation ran.

# 2026-08-31 — v0.1.21 Hotfix 3 Readiness/Pricing/UAT Consistency

- Focused readiness and retained-Hotfix source tests passed after correcting the new test harness path; no application defect was exposed by the first pass.
- Sixteen task-local bilingual desktop/mobile visual cases passed mixed-language, exposed-variable/raw-state, and horizontal-overflow checks; two representative captures were inspected.
- One Node 20.20.2 Vite build passed; manifest SHA-256 `cc095c1ecdfd4690ed5e500b18c78c63d74043ae0933e548c72f5111a92fdf78`.
- No database, Artisan, migration, seed, purge, broad suite, PDF, production, tag, push, or remote operation ran.

# 2026-09-01 — v0.1.22 cumulative stabilization final verification

- Focused grouped PHPUnit: 70 tests, 692 assertions, all passed across the 11 named V016/M51–M54/V021/V022 files; no broad suite ran.
- Node runtime: `/opt/cpanel/ea-nodejs20/bin/node` v20.20.2 and `/opt/cpanel/ea-nodejs20/bin/npm` 10.8.2. One production Vite build passed using the mandated Node 20 PATH.
- Printing visuals: eight local Playwright fixture cases passed for Arabic/English, desktop/mobile, and template-library/printer-scope families; correct RTL/LTR, no mixed-language leakage, no document overflow, and minimum 44 px controls. Four representative captures were visually inspected.
- Isolated MariaDB: complete 96-migration chain passed in a temporary user-owned socket-only MariaDB. Migration `000102` column, FK, composite uniqueness, rollback and remigrate passed. A first rollback exposed MariaDB error 1553 and the down migration was corrected; the fresh repeat passed. The earlier `000101` run verified schema, FKs, uniqueness and preservation of legacy `template_name` through rollback/remigrate.
- Cleanup: both exact temporary databases, MariaDB processes and data directories were removed. No production/shared database or symlink target was accessed.
# 2026-08-31 — v0.1.21 Final Stabilization

- Focused M54 PHPUnit: passed 8 tests / 62 assertions.
- Focused route pass: purchasing-invoice route subset registered successfully.
- Isolated Blade/Flux render probe: passed for the six requested UI contracts and all scoped M54 Flux components/icons.
- No database, migration, broad suite, frontend build, PDF, browser-control, or unrelated verification ran.
# 2026-09-01 — v0.1.22-hotfix1 post-deployment focused gate

- `tests/Unit/V022Hotfix1PostDeploymentTest.php`: 4 passed, 59 assertions.
- `tests/Unit/V0122Hotfix2CorrectiveTest.php`: 4 passed, 26 assertions. Isolated authenticated browser evidence: Product Card save/reload `25.00`, readiness ready, EAN-13 `4006381333931` decoded twice, Code 128 `TESTLOCAL0001` decoded once, no-barcode state preservation, invoice canonical redirect, 50×30 mm thermal and A4 visuals; no console errors/overflow. Disposable MariaDB only (97 migrations); no shared or production data.
- Changed/new PHP syntax, Arabic/English JSON parsing (`5541`/`2360` keys), and `git diff --check`: passed.
- Barcode assertions: valid EAN-13 checksum/pattern output and Code 128-B start/data/checksum/stop SVG structure passed for `4006381333931` and `TJ-ABC-001`.
- Preserved visual evidence: 8 previously completed Arabic/English desktop/mobile printing cases; not repeated under the one-pass rule.
- Node 20.20.2/npm 10.8.2 Vite 8.2.0 build: passed. Manifest SHA-256 `cc095c1ecdfd4690ed5e500b18c78c63d74043ae0933e548c72f5111a92fdf78`.
- Database: no migration executed in this correction pass. Production/shared databases were not accessed. Physical-printer acceptance remains outstanding.
# 2026-09-02 — v0.1.22-hotfix3 post-review closure

- Focused correction test passed 9 tests / 143 assertions with zero failures.
- Authenticated isolated-browser verification passed 3 real-route PDFs and 11 Arabic desktop/mobile route cases. Raster decoding passed 3/3 Code 128 copies and 1/1 thermal plus 1/1 A4 EAN-13 copies; all generated pages were rasterized. Zero console errors and zero horizontal document overflow were recorded.
- Disposable MariaDB `rajeh_hotfix3_verify` reports all 97 migrations ran. PHP syntax, Blade compilation, Arabic/English JSON, and diff checks passed once. No broad suite or repeat Vite build ran.
# 2026-09-03 — v0.1.22-hotfix9 focused verification

- PHPUnit: 12 tests, 78 assertions passed across Hotfix9 navigation cleanup plus Hotfix6/Hotfix7 preservation contracts.
- PHP syntax passed for every changed PHP/config/Blade/test file; Blade cache compilation passed; Arabic and English locale JSON parsed successfully; `git diff --check` passed.
- Vite 8.2.0 production build passed with Node 20; `public/build/manifest.json` SHA-256: `b0d6e69cb067407c8ecc07e72eed30f06fb88a4050a3bd55f4f526c5c20606df`. The host-default Node 16 attempt failed before asset emission because Vite 8 requires a newer Node runtime.
- Disposable MariaDB database `toyjoy_hotfix9_verify` migrated through `000106`. No Hotfix9 migration exists or was required. Demo seeding stopped on its pre-existing supplier-settlement fixture incompatibility after the canonical production seed had completed; this did not affect the focused authenticated checks.
- Authenticated Chromium passed Arabic and English desktop sidebars, Arabic mobile drawer, 20-card Setup Center, and Pricing Lists setup context with one retained top pricing summary. Results: zero console/page errors, zero horizontal overflow, no Party/System Settings/duplicate administration/footer-profile navigation, and all five Essentials leaves plus Roles & Permissions present.
- Isolated route profiling recorded dashboard 89 queries, Pricing Lists setup 76, Initial Setup 72, and inventory balances 13. Hotfix9 adds no query-producing sidebar code; it removes the Party policy/booking readiness reads from source and removes the page-level duplicate pricing-readiness consumer. Inventory route source is unchanged from Hotfix8.

# 2026-09-03 — v0.1.22-hotfix9 view-cache correction

- PHP 8.5.9 `optimize:clear` passed with task-local `CACHE_STORE=file`, `SESSION_DRIVER=file`, and `QUEUE_CONNECTION=sync` overrides; the worktree has no `.env`, and the initial unoverridden attempt failed by defaulting the cache store to unavailable `root@localhost` database credentials.
- PHP 8.5.9 `view:cache` passed with the same isolated overrides after the pricing-label view was decomposed into seven bounded partials. No vendor, Livewire compiler, PCRE-limit, or environment configuration change was made.
- Preserved authenticated Chromium artifact covers Arabic RTL and English LTR pricing labels, Pricing Lists and navigation: 20 unique setup leaves, Party deferred, one readiness summary, zero console/page errors and zero horizontal overflow.
- No automated suite or repeated frontend build was run for this correction. No production/shared runtime or database was accessed, and `storage/storage` remained absent.
