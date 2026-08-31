# Test and Verification Results

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
# 2026-08-31 — v0.1.21 Final Stabilization

- Focused M54 PHPUnit: passed 8 tests / 62 assertions.
- Focused route pass: purchasing-invoice route subset registered successfully.
- Isolated Blade/Flux render probe: passed for the six requested UI contracts and all scoped M54 Flux components/icons.
- No database, migration, broad suite, frontend build, PDF, browser-control, or unrelated verification ran.
