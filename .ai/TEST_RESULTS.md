# Test Results

## 2026-08-20 — Access, role, supplier-group, and branch/store remediation

- **Database boundary:** only the disposable MariaDB schema `toyjoy_client_feedback_20260819` at `127.0.0.1:3307` was used; SQLite, port 3306, Production, and `C:\xampp\mysql\data` were not touched. The named `mysqld` listener/schema were verified before testing.
- **TDD RED → GREEN:** the initial `AccessMasterManagementTest` run was RED at **3 tests, 0 passed, 2 assertions**: `/admin/roles` returned 404 and the Access/Supplier pages lacked the expected discovery controls. After the smallest implementation, the focused result is **8 tests / 39 assertions PASS**. It covers guarded role routes, view-only mutation denial, local role creation, permission persistence/audit, canonical and sensitive-grant guards, supplier-group visibility/validation/persistence/audit, direct branch/store and selling-store-mapping updates with no approval records, and direct-edit denial for a scoped branch/store viewer.
- **Static:** PHP syntax passed for the new action and focused test; route discovery reports the two guarded routes. `view:cache` was started but did not complete before the bounded wait was stopped, so no Blade-cache pass is claimed.
- **Browser limitation:** a local Laravel server was started only against the named disposable schema and temporary QA administrator/viewer data were prepared there. The in-app browser's first navigation to `localhost:8000` occurred before the server was listening; after the server was safely started (then also retried on the original port), Browser Use blocked subsequent local navigation by URL policy. Therefore no headed visual, RTL/LTR, desktop, 390px, or browser-persistence result is claimed. The temporary server was stopped afterward.

## 2026-08-20 — Translation override Local/Dev verification (latest focused evidence)

- **Database boundary:** only disposable MariaDB `toyjoy_translation_overrides_20260820` on `127.0.0.1:3307` (root, blank password); no SQLite or Production connection.
- **TDD:** the first focused run was RED before feature execution because concurrent migration activity reached an existing `product_suppliers` table. A serial fresh migration then completed **81 migrations**. Later verification exposed deferred-loader replacement and JSON/PHP key shadowing; both causes were corrected. The latest focused result after bilingual atomic-save coverage is **5 tests / 31 assertions PASS**, covering route authorization, JSON and effective PHP-group overrides, reset/audit behavior, unknown-key rejection, placeholder preservation, and rollback of a partially invalid bilingual save.
- **Static:** PHP lint passed for the seven changed PHP files; `admin.translations` route discovery returned one guarded Livewire route; Arabic and English JSON parsed successfully. `view:cache` was started but its completion output was not captured, so no Blade-cache pass is claimed. Full `git diff --check` still reports pre-existing unrelated whitespace in `SESSION_SUMMARY.md` and `SaveProductAction.php`; the targeted changed-file diff check passed.
- **Not fully run:** browser coverage. UAT, physical-device checks, Production actions, commit, and push were not run.

## 2026-08-20 — Expanded Master final local evidence checkpoint

- **Minimum and multi-branch suites:** §66 minimum scenario matrix passed **15 tests / 118 assertions**. The final multi-branch acceptance filter passed **20 tests / 104 assertions** after aligning legacy drawer fixtures with the required active selling-location mapping; production drawer validation was not weakened.
- **Settings:** payment/tax feature and calculation filters passed **14 tests / 63 assertions**; sequence acceptance passed **5 tests / 28 assertions**, with the narrower company-settings/TSK-009 sequence filter also passing **3 tests / 16 assertions**; settings audit/authorization passed **6 tests / 38 assertions**. Printer scope verification passed **3 tests / 9 assertions**, and the runtime Location → Branch → Global resolver/cross-branch denial is also included in the §66 PASS.
- **Customer/master security:** category hierarchy/optional English passed **2/5**; child profiles **2/18**; duplicate email **1/3**; structured names **1/6**; customer groups **2/4**; recipient resolution **2/5**; cross-customer child denial **1/3**; cross-supplier contact/destination denial **1/2**; supplier-group company scope **1/1**; PO term auto-fill/override/persistence/authorization **1/4**.
- **Migration/seeder:** isolated MariaDB completed all 75 migrations; after correcting printer-scope down-order, the final batch rollback and second forward migration passed. `CanonicalAuthorizationSeeder` passed twice with stable hashes and final invariants of 9 roles, 400 permissions, 411 role-permission links, and zero companies.
- **Browser:** authenticated Arabic RTL and English LTR desktop/mobile batches passed the affected setup/master/settings/forms routes with no 500/error pages, no warning/error console logs, and no horizontal overflow at the tested 390px/CSS-375 viewport. Customer QA passed policy-key rendering, structured-name creation, consent, duplicate-email warning, and child add/edit. Supplier/PO QA passed term auto-fill, explicit override retention, draft save/reload, and RTL print detail with total 251.00. Child deactivation is proven by focused feature tests; the browser confirmation wrapper timed out, so no browser deactivation claim is made.
- **Owner/hardware boundary:** the Initial Setup owner-decision matrix and real permission-aware CTAs passed source/route/Blade checks and are included in the bilingual setup browser batches; no decision was approved or persisted. No physical printer/device, human UAT, Production, release approval, commit, or push is claimed.

## 2026-08-20 — Batch B closure and local MariaDB recovery

- **Database boundary:** serial verification used only the disposable XAMPP MariaDB client-feedback database on `127.0.0.1:3307`; no SQLite or Production connection was used. The serial Batch B run passed **5/5 tests with 25 assertions**. The §49 supplier order-recipient resolver passed **2 tests with 5 assertions** and fails closed when no designated purchase-order recipient exists.
- **Product import:** focused evidence covered staged CSV mapping, independently authorized reviewer approval, and requester self-approval rejection. No owner business import file was uploaded and no Production data was mutated.
- **Browser:** authenticated headed Chromium passed all six `/admin/settings` tabs in Arabic RTL at 390px with no horizontal overflow and no console warnings/errors. The settings 500/Blade parse/Livewire multiple-root/Alpine dirty-state chain was fixed and rechecked. Supplier, branch, store, and customer-group empty-submit validation passed; the cash-drawer modal opened; POS at 375px had no horizontal overflow.
- **MariaDB recovery:** before recovery, the shared data directory was copied exactly to `C:\xampp\mysql\data-recovery-copy-20260820-004101`. Read-only forced-recovery checks reported **121/121 tables** `CHECK TABLE QUICK OK` and **121/121** readable table-count queries. A clean XAMPP datadir from the bundled backup replaced the corrupt active directory, which was preserved at `C:\xampp\mysql\data-corrupt-active-20260820-004101`; MariaDB returned to port 3306. The recovered `toyjoy_local` dump restored successfully with 121 tables, one company, one branch, and ten users; four pending migrations then completed.
- **Boundary:** physical printers/devices, external destinations, owner business data, Production, UAT, release approval, commit, and push were not claimed. Recovery copies/work directories remain retained.

The older blocked/incomplete Batch B entries later in this file are historical records of pre-recovery attempts; the 2026-08-20 closure entry above is the controlling latest result for CF-13/14.

## 2026-08-20 — Browser verification blocked by local database availability

- A Laravel server was started at `http://127.0.0.1:8000` for the requested browser pass.
- The browser root-page navigation timed out because the local `.env` targets MySQL/MariaDB at `127.0.0.1:3306`, database `toyjoy_local`, and no listener was available on port 3306.
- Login, forms, persistence, validation, RTL/LTR, and other application flows could not be exercised in this attempt. The Laravel server was stopped afterward.
- No automated test suite, database mutation, migration, physical-device check, UAT, commit, or push occurred. This is an environment blocker, not a pass/fail result for the application.

## 2026-08-19 — CR-002 company identity persistence

- Database boundary: only disposable XAMPP MariaDB `toyjoy_client_feedback_20260819`; MariaDB was stopped after Terra and Sol verification. No SQLite or Production connection was used.
- RED evidence: 6 focused tests produced 5 green / 81 assertions and 1 intentional failure proving duplicate company rows were silently resolved with `Company::first()`. Headed pre-fix evidence also proved the Save-labelled control only previewed and dirty edits could be discarded without warning.
- Final focused PHPUnit: Terra and Sol independently passed 7 tests / 95 assertions from `CompanyIdentityPersistenceTest`.
- Affected module regression: Terra and Sol independently passed 15 tests / 87 assertions from `CompanySettingsTest`.
- Final visible headed Chromium: 1/1 passed in 29.1 seconds (24.0-second test body), covering server-side validation with retained input, Review/Confirm, localized success, clean/dirty behavior, native reload warning, actual sidebar `wire:navigate` cancel/confirm, persistence after reload and re-login, English/LTR 1280×900, and Arabic/RTL 390×844. No console errors, page errors, or failed requests were captured.
- Browser evidence: `testing/e2e/results/cr002-company-identity-per-8bf12-persists-confirmed-identity-chromium/` contains five named screenshots, the final screenshot, and `trace.zip`.
- Static gates: PHP syntax, Blade cache, focused Pint `--test`, and `git diff --check` passed. Sol's first exact Pint run found four formatting fixers in the modified settings view; Terra applied formatting only and Sol's rerun passed.

## 2026-08-19 - Focused defect and fixture remediation

- Database boundary: focused PHPUnit work used only MariaDB `toyjoy_phase1_remediation_20260818`. No Production database or owner business data was used.
- US-002 RED: headed Chromium reproduced a server-side Livewire state change while the Flux dialog remained closed; the persisted computed lookup also rehydrated invalid values. GREEN: the dedicated Product Masters browser specification passed 1 test after removing computed persistence and using the supported named trigger/modal lifecycle. The focused PHP test passed 1 test/2 assertions.
- US-017 RED: the POS rendered MAIN/MAIN-SALES for a cashier whose only active assignment was ALT-POS/ALT-SALES/ALT-DR, and the no-shift state had no explicit disablement. GREEN: `PosShiftDerivedContextTest` passed 2 tests/9 assertions. PHP syntax and POS route discovery passed. A broader checkout suite initially could not load the deleted `CanonicalAuthorizationSeeder`; that infrastructure blocker was repaired separately.
- Authorization compatibility: a previously blocked checkout case passed 1 test/3 assertions after adding the authorization-only compatibility seeder. A different checkout text assertion remains outside that compatibility result and was not claimed as passing.
- US-046 RED evidence remains the authorized 2026-08-18 focused browser result where visible Page Guide and Appearance launchers opened no drawers and sent no preference request. GREEN: the Vite build passed and the dedicated Livewire-navigation Chromium specification passed 1/1 in 6.4 seconds after moving registration to the Vite module. `git diff --check` passed.
- Remediation fixtures: `RemediationSeederTest` passed 4 tests/17 assertions, covering wrong-database refusal, missing runtime-password refusal, exclusion from normal seeding, and idempotent target-database creation. The focused compatibility test and all changed fixture PHP files passed syntax checks; `git diff --check` passed.
- Not run or not complete in this checkpoint: a full remediation suite, all 33 serial story workflows, the offline POS device/queue/sync/conflict lifecycle, complete concurrency matrix, final mobile/RTL/LTR/accessibility matrix, backup creation/destructive restore, physical devices/printers, external backup, Production, UAT, release approval, commit, or push.

## 2026-08-15 - Owner-authorized local ERP demo-seeder verification

- Database: isolated XAMPP MariaDB schema `toyjoy_demo_seeder_20260815_v2`. It was created specifically for this work; only normal migrations were run. No existing database was wiped, truncated, or dropped.
- Focused PHPUnit: `DemoErpSeederTest` passed (1 test, 25 assertions). It covers the local/testing baseline plus the complete purchase receipt, supplier return, transfer, POS shift, paid sale/payment, customer link, loyalty entry prerequisite, stock values, and a second seed invocation without duplicate Demo records.
- `php artisan db:seed --force` was run twice against the isolated schema under `APP_ENV=local`, then once more after the final transaction wrapper; all completed successfully and added no Demo record after the first run.
- MariaDB integrity queries found exactly one Product, Customer, Purchase Order, Purchase Invoice, Purchase Return, Stock Transfer, and Sale for their stable Demo identifiers; all documents reached their expected `received`/`approved` states. POS payment total equals sale total and paid total (15.00). Warehouse stock reconciled to 1 unit / 10.0000 and POS-store stock to 9 units / 90.0000, each matching its summed stock movements; no stock remained in transit. The Customer has one nonempty `public_id` and one earned-loyalty ledger entry (15 points). Duplicate-document query returned zero.
- No chart-of-accounts, journal, bank/treasury, accounts-receivable/payable, supplier-payment, or accounting-entry module exists in the current application, so Debit=Credit and supplier/customer financial balances are not applicable and no fabricated accounting records were added.

## 2026-08-15 — Owner-authorized Party completion verification

- Database: isolated XAMPP MariaDB schema `toyjoy_party_completion_20260814`; rebuilt with all migrations and `ProductionSeeder`, then populated only with `PartyCompletionBrowserSeeder` fixtures for the authenticated browser run.
- Party lifecycle: 16 passed, 93 assertions.
- Rental-asset Party integration: 5 passed, 33 assertions. The combined command exceeded the runner's five-minute cap; the same five tests passed in two focused segments (1 test/16 assertions and 4 tests/17 assertions).
- Party Wallet: 3 passed, 22 assertions.
- Party quotation: 1 passed, 16 assertions.
- Authenticated Chromium: 1 passed in 43.6 seconds, covering Party booking creation with a catalog product, working invoice UI, rental assets, Party Wallet, quotations, mobile viewport, Arabic `lang`/RTL direction, horizontal overflow, and page errors.
- Vite production build passed. The existing optional `fontaine` and plugin-timing warnings were emitted.
- Focused PHP syntax and Pint passed. Blade cache passed; route cache passed after clearing the previously generated route cache. `git diff --check` passed.
- One intermediate browser rerun failed authentication after focused `RefreshDatabase` tests removed the disposable fixtures; the isolated schema was intentionally rebuilt/reseeded and the final Chromium run passed.
- No Production database, Production data, commit, or push was involved.

## 2026-08-18 - Owner-requested UI-only user-flow audit

- Application: local `http://127.0.0.1:8000`, authenticated through the visible `admin` login form. Chromium UI checks used desktop 1280x900 and mobile 390x844 viewports.
- Coverage: navigated the documented user-flow entry screens and linked variants across authentication, administration, catalog, purchasing, pricing, inventory, POS/cash/returns, customers/wallets, Party/rental assets, quotations, reports, audit, and offline readiness.
- Observed UI defects: Product `Add Product` produced HTTP 500 from the Livewire update request; POS displayed branch/store context while `/pos/shift` and `/pos/suspended` reported no selling-store access; category form controls lacked programmatic label associations; empty category validation exposed internal Livewire state paths; several POS mobile controls measured below the project 44px touch-target guideline; the empty reports page repeated empty-source headings and measured about 20,082px tall on mobile.
- No data was submitted or created during this audit. No PHPUnit, Pest, Playwright test suite, Cypress suite, or other automated test suite was run. Findings are recorded in `docs/06-user-flows-ui-audit-2026-08-18.md`.

## 2026-08-18 - Aggressive headed Phase 1 UI verification

- Browser: headed Chromium, visible desktop 1280x900 and mobile 390x844 sessions against `http://127.0.0.1:8000`, authenticated only as the existing local `admin` account.
- UI evidence: reproduced Product `Add Product` Livewire HTTP 500, POS versus shift selling-store contradiction, missing category-form label associations, internal Livewire validation names, undersized POS controls, repeated empty report headings, canonical direct-route 404s, raw JSON backup output, and Arabic error pages during an English/LTR operational session.
- Positive checks: valid login, generic invalid login, fresh empty-login rejection, logout and protected-route redirect, Arabic login `dir=rtl`/`lang=ar`, rendered 403 and 404 pages, category validation blocking save, and no horizontal overflow in sampled desktop/mobile screens.
- Screenshots captured outside the repository: `%TEMP%/toyjoy-phase1-ui-audit-20260818/product-add-500.png` and `%TEMP%/toyjoy-phase1-ui-audit-20260818/pos-mobile.png`.
- No save, approval, delete, export, business transaction, permission grant, or database mutation was performed. No automated test suite, direct backend-forgery test, concurrency race, backup restore, attachment test, or multi-role matrix ran. Consolidated evidence and the Phase Gate verdict are in `docs/06-user-flows-ui-audit-2026-08-18.md`.

## 2026-08-18 - Phase 1 audit remediation, partial focused evidence

- Database: isolated XAMPP MariaDB schema `toyjoy_phase1_remediation_20260818`, created specifically for this remediation pass. The focused suite rebuilt only this disposable schema through `RefreshDatabase`; no Production database or owner business data was used.
- Focused PHPUnit: 8 tests and 39 assertions passed under `phpunit.remediation.xml`. Coverage proves inactive super-administrator gate/login rejection; administrator-created username login identity; protected `/profile` and documented settings compatibility redirects; English error surfaces; Backup & Restore UI rather than raw JSON; authenticated Notifications empty state; and Category form stable ID/label plus human validation attribute behavior.
- Static verification: PHP syntax passed for all changed PHP/Blade sources; targeted Pint passed after formatting; Blade cache and route cache both completed; `git diff --check` passed.
- Browser-control, real parallel races, POS/shift context, Product modal browser regression, attachment lifecycle, actual backup creation/isolated restore, physical printer, external backup destination, and owner-data scenarios were not run in this partial pass and remain unverified.

## 2026-08-18 - Phase 1 audit remediation re-verification

- Confirmed MariaDB 10.4.32 and the dedicated disposable schema `toyjoy_phase1_remediation_20260818` before rerunning the focused remediation suite.
- `php artisan test --configuration=phpunit.remediation.xml` passed again: 8 tests, 39 assertions.
- Syntax checks and targeted Pint passed; Blade cache passed. The first route-cache attempt encountered Laravel's compiled-route-collection cache-state TypeError, so route cache was cleared and rebuilt successfully. Route discovery then confirmed `/admin/system/backups`, `/notifications`, `/admin/company`, and `/profile` compatibility registrations. `git diff --check` passed.
- No headed browser check, external backup/restore, hardware check, Production connection, commit, or push occurred.

## 2026-08-18 - Phase 1 audit remediation headed Chromium check

- Database/server: rebuilt only `toyjoy_phase1_remediation_20260818` with all migrations and `ProductionSeeder`, then ran a local `APP_ENV=testing` server at `127.0.0.1:8821`; the server was stopped after the run.
- Headed Chromium: `testing/e2e/phase1-remediation.spec.js` reached login, `/profile`, `/admin/company`, Backup & Restore, Notifications, and Category form label/validation states successfully. It then failed at Product `Add Product`: the expected `Create product identity` dialog never appeared within five seconds. Playwright retained the failure screenshot, trace, and error context under `testing/e2e/results/phase1-remediation-Phase-1-5d5bb-s-render-in-headed-Chromium-chromium/`.
- This is a reproduced unresolved Product modal defect. The browser check stopped at that failure; the 404/error-surface assertion and remaining browser scenarios did not run in this execution.

## 2026-08-18 - Product-only headed Chromium rerun

- Re-ran only the previously unpassed Product Add Product check, skipping all previously passed route, backup, notification, and Category assertions. The isolated server used `toyjoy_phase1_remediation_20260818` and was stopped afterward.
- The product dialog again did not appear. Playwright observed no Livewire response at HTTP 400+ before the missing-dialog assertion, so this rerun does not support calling it a current HTTP 500; it establishes a repeatable browser modal-state failure. Updated screenshot, trace, and error context are retained under `testing/e2e/results/phase1-remediation-product-3efbb--without-a-Livewire-failure-chromium/`.

## 2026-08-18 - Canonical user-story UI-only retest

- Scope: all 33 canonical stories in `docs/05-user-stories.md` (US-001 through US-032 plus US-046), exercised only through visible headed Chromium UI. The story report is `docs/05-user-stories-ui-retest-2026-08-18.md`.
- Environment: local server `http://127.0.0.1:8832`; isolated MariaDB `toyjoy_phase1_remediation_20260818`; normal testing seed (`ProductionSeeder` plus `DemoErpSeeder`); bootstrap `admin`; desktop 1280x900; mobile 390x844; English/LTR plus Arabic/RTL smoke.
- Full audit harness completed 33 story records in 8.8 minutes. Final reviewed classification after the focused prerequisite check: 3 PASS, 23 PARTIAL, 4 BLOCKED, and 3 FAIL. A passing harness means evidence collection completed, not that every story passed.
- Reproduced failures: Product Masters `Add Product` did not open its dialog after five seconds although the direct create form rendered; POS selected MAIN/MAIN-SALES with no open shift and displayed the legitimate DEMO product as unpriced/out of stock with Add disabled; Page Guide and Appearance Customizer launchers were visible but neither drawer opened after focused 1.5-second waits, and no preference request occurred.
- Focused Party check found the visible Party store select contained exactly one option, `Choose Party store`, so US-025 is blocked; US-026 and US-027 are also blocked by absent Party invoice/order prerequisites. US-008 is blocked by the absence of an approved open-price product policy.
- Representative mobile pages (Dashboard, Product Masters, POS, Reports, Party Bookings) returned 200 at 390x844 with zero measured document-level horizontal overflow. Party Bookings then rendered `lang=ar`, `dir=rtl`, also with zero measured document-level overflow.
- Browser runtime evidence recorded zero page errors and zero console errors. The recorded `net::ERR_ABORTED` resource requests were navigation cancellations while moving between pages, not HTTP failure responses.
- Local evidence is under ignored directories `artifacts/all-user-stories-ui-retest-2026-08-18T18-18-04-292Z/` and `artifacts/all-user-stories-ui-focused-retest-2026-08-18T18-28-37-809Z/`.
- No PHPUnit, Pest, direct controller/API test, direct database story assertion, Production connection, physical printer/device check, external backup/restore, commit, or push occurred. The disposable remediation schema was rebuilt and seeded only to provide legitimate UI prerequisites.

## 2026-08-19 — CF-02 branch identity consistency

- Database: disposable `toyjoy_client_feedback_20260819` on an isolated XAMPP MariaDB data directory at port 3307. The shared `C:\xampp\mysql\data` instance failed InnoDB startup and was left untouched.
- Focused PHPUnit: `vendor\bin\phpunit --configuration phpunit.cr003.xml` passed 1/1 with 15 assertions in Sol's final independent run.
- Headed Chromium: 1/1 passed in 19.1 seconds (10.1-second test body). It edited branch code and Arabic/English names, saved, reloaded Branches, proved the old code absent, reloaded Stores, and found exactly two linked rows with the new identity and none with the old code.
- Evidence: `testing/e2e/results/cr003-branch-source-of-tru-b34c6-anches-to-its-linked-Stores-chromium/`.
- PHP syntax, Node syntax, and `git diff --check` passed. The isolated MariaDB process was verified and stopped after testing.

## 2026-08-19 — CF-03 six-branch creation

- Focused PHPUnit: `phpunit.cf003.xml` passed 2/2 with 28 assertions after an observed RED of 1/2 passing and 23 assertions.
- Existing branch duplicate-code regression: 1/1 passed with 5 assertions.
- Headed Chromium: 1/1 passed in 52.3 seconds (40.1-second test body), creating five branches after baseline `MAIN`, rejecting a normalized duplicate inline, and preserving six identities after reload and re-login.
- Evidence: `testing/e2e/results/cf003-branch-creation-capa-560cd-s-after-reload-and-re-login-chromium/`.
- Blade cache, PHP syntax, Node syntax, focused Pint, and `git diff --check` passed. Only isolated MariaDB port 3307/database `toyjoy_client_feedback_20260819` was used; its verified process was stopped.

## 2026-08-19 — CF-04 branch timezone inheritance

- Database: disposable `toyjoy_client_feedback_20260819` on isolated XAMPP MariaDB datadir `C:\xampp\tmp\toyjoy_mariadb_cf_20260819`, `127.0.0.1:3307`. Shared `C:\xampp\mysql\data` was not touched.
- RED: `php vendor/bin/phpunit -c phpunit.cf004.xml` initially ran 5 tests with 2 passed / 3 failed (8 assertions): create default and save/reload received `UTC` instead of `Africa/Cairo`; an Action update missing timezone overwrote `Asia/Riyadh` with `UTC`.
- GREEN: `php vendor/bin/phpunit -c phpunit.cf004.xml` passed 5/5 with 9 assertions. Four repeat runs passed, most recently in 5.298 seconds.
- Headed Chromium: `PLAYWRIGHT_BASE_URL=http://127.0.0.1:8014 npx playwright test testing/e2e/cf004-branch-timezone-inheritance.spec.js --project=chromium --headed --workers=1` passed 1/1 repeatedly; latest run completed in 28.2 seconds. It proved create default, explicit override, reload persistence, changed company default, and existing-branch edit preservation with no captured console/page/request failures.
- Static gates: PHP syntax, Node syntax, focused Pint, Blade cache, and `git diff --check` passed.
- The exact verified isolated mysqld was shut down after each run; the final shutdown process exited 0 and post-shutdown ping was refused as expected.

## 2026-08-19 — CF-05 branch/warehouse relationship closure

- **Problem/root cause:** Branch/warehouse relationship and counts were unclear because the relationship/count scope, terminology, and branch selector context were incomplete or ambiguous.
- **Changed behavior/UX:** Active warehouse counts and authorized branch linkage are correct; Location/Warehouse is distinct from POS; tables are readable at 1280x900; create/edit selectors show full code + name and retain the selection after reload.
- **Focused PHPUnit:** **3/3 passed, 21 assertions** (`php vendor/bin/phpunit --configuration=phpunit.cf005.xml --do-not-cache-result`).
- **Headed Chromium:** **1/1 at 1280x900**, with **0 console errors, page errors, or failed requests**. Screenshots: `cf005-branch-warehouse-en-headed.png`, `cf005-locations-table-1280x900.png`, `cf005-location-create-selected.png`, `cf005-location-edit-selected.png`, `cf005-location-edit-reloaded.png`.
- **Sol:** PASS. **Next:** CF-06.

## 2026-08-19 — CF-06 POS linkage closure

- Changed mapping authority and clear admin/POS UX; focused tests **3/3, 20 assertions**; Browser **PASS 1/1**, zero errors; **Sol PASS**; **Next:** CF-07.

## 2026-08-19 — CF-07 cash drawer association closure

- Changed canonical branch→POS cash-drawer association and verified create/edit/reload context with POS/shift headers; focused tests **2/2, 10 assertions**; Browser **PASS**, zero errors; **Sol PASS**; **Next:** CF-08.

## 2026-08-19 — CF-08 archive safety closure; Batch A activation

- Backend focused result: **1/1 test, 9 assertions passed**.
- Headed core archive UI verified after assets were fixed: archive modal, cancel, submit, and pending states. Later automation stopped on an approval-inbox heading locator only; the full spec is not claimed green.
- **Sol: PASS**, based on the client archive scenario. Test stack and policy/static checks were stopped/passed as previously observed; no additional test run was performed by this state update.
- **Next:** Batch A is closed: CF-09/10/11/12/15 are **DONE**. Batch B is active for CF-13/14.

## 2026-08-19 — Batch B checkpoint (incomplete verification)

- Static checks passed: PHP lint, Blade view-cache compilation, route-cache/discovery, and `git diff --check`.
- Focused MariaDB and headed-browser checks were not run because the owner directed that no separate verification environment be created for this checkpoint.
- Requirements 36/37 category-code behavior (optional English fallback and hierarchy support) was not exercised against MariaDB, PHPUnit, or a browser in this checkpoint. No pass claim is made beyond the static checks.

## 2026-08-19 — Wave 1/2 local implementation checkpoint (static-only)

- Static checks recorded as passed for the local surfaces covering Req 15/16 (actionable setup checklist and setup/operations separation), Req 38/42 (customer registration prerequisite clarity and hierarchical customer groups), and Req 46–48 (supplier groups, structured contacts, and communication destinations): PHP syntax, Blade view compilation, route discovery, and `git diff --check`.
- No MariaDB/database or migration run, local server run, PHPUnit/Pest/other automated test, headed-browser check, physical-device check, UAT, commit, or push occurred for this checkpoint. No runtime pass claim is made for these requirements.
- At this historical static-only checkpoint CF-13 and CF-14 remained ACTIVE. The final 2026-08-20 entries at the top of this file supersede that status and close both for the evidenced Local/Dev scope.

## 2026-08-19 — Wave 2 local UX checkpoint (static-only)

- **Scope recorded:** Product/import UX for Requirements 17/52/56/59/60; settings terminology, configuration-history labeling, business help, and bilingual copy for Requirements 18/19/30/33/35/58; and static inspection of the existing MR64 regression surfaces.
- **Static checks recorded:** PHP syntax, Blade view compilation, route discovery, and `git diff --check` passed for the local work.
- **MR64 limit:** Static inspection of Product Add, POS operating context, Page Guide, and Appearance Customizer found no new confirmed direct defect. This is not evidence that those runtime/story defects are fixed or rechecked.
- **Not run:** No MariaDB/database or migration, local server, PHPUnit/Pest/other automated test, headed-browser check, physical-device check, UAT, commit, or push occurred for this checkpoint. No runtime, persistence, authorization, concurrency, or cross-browser result is claimed.

## 2026-08-20 — Wave 3 local identity/taxonomy checkpoint (static-only)

- Targeted PHP lint passed for `CreateCustomerAction`, `UpdateCustomerAction`, `Customer`, `PhoneNormalizer`, `SaveStoreAction`, and `Branch`.
- `git diff --check` passed.
- `php artisan view:cache` was attempted but returned no usable completion output within the command window; Blade-cache success is therefore not claimed.
- No MariaDB/database or migration, PHPUnit/Pest/other automated test, headed-browser check, physical-device check, UAT, commit, or push ran for this checkpoint. No runtime or requirement-level pass is claimed.

## 2026-08-20 — Wave 4 local scope/loyalty/wallet checkpoint (static-only)

- Scope recorded: Requirements 53–55 (readiness truth, multi-branch scope, and inheritance wording) plus Requirements 44–45 (Loyalty CTA and Product Wallet configuration UX).
- No test suite, MariaDB/database or migration, local server, PHPUnit/Pest, headed-browser, physical-device, UAT, commit, or push action was run in this state-only checkpoint. No runtime, persistence, authorization, RTL/LTR, or requirement-level pass is claimed.
- Open boundary: matching values are not asserted to be inherited because the current schema has no provenance/source marker. An owner-approved source-marker or nullable-override decision remains required before full inheritance verification.
- 2026-08-20: Shared `C:\xampp\mysql\data` showed corruption/Crash Recovery evidence and was not touched; no authenticated form, persistence, or database test ran. Temporary Laravel 8000 (array cache/file sessions) verified Arabic RTL auth routes, 390x844 no-overflow login, zero console warnings/errors, protected redirects, and branches/stores after retry. PHP lint 165, route discovery 240, JSON translations, `view:cache`, and `git diff --check` passed. Focused test timed out without result. Queue 8 DONE/2 ACTIVE; 15 waves open; no CF/Wave closure claimed.

## 2026-08-20 — Translation editor authenticated browser QA

- Environment: only `127.0.0.1:3307/toyjoy_client_feedback_20260819`; migration `2026_08_20_000092_create_translation_overrides_table` was already recorded and `php artisan migrate --force` completed with nothing pending. `LocalAuthSeeder` created disposable local QA roles. Laravel ran at `http://127.0.0.1:8003` with explicit 3307 settings.
- In the authenticated in-app browser, the `Setup / Master Data` sidebar group expanded without navigation; its permission-gated `Translation editor` child was visible, clicked to `/admin/translations`, retained the expanded parent, and carried the active state. Arabic RTL desktop and 390x844 mobile rendered without document horizontal overflow or captured console warnings/errors.
- Save/reload/reset: searched `Dashboard`, saved reversible Arabic/English QA values, reloaded the editor, and confirmed both values in Arabic RTL and English LTR dashboard navigation. `Reset` restored shipped `لوحة المتابعة` / `Dashboard`; direct MariaDB verification then showed zero `translation_overrides` rows.
- Placeholder validation: attempted `Records: :count` with the Arabic placeholder removed and a valid-looking English value. The editor displayed `Keep the same placeholders as the base translation.` and the database remained empty, proving no partial persistence.
- Authorization: a disposable local branch-manager account received the expected direct `/admin/translations` 403 denial. No application code or automated test suite ran; no UAT, Production, commit, or push claim. Per user direction, the isolated Laravel server at port 8003 remains running.

## 2026-08-20 — Admin master archive/delete UI check

- Environment: local Laravel server `http://127.0.0.1:8003`, Local System Administrator session, and disposable XAMPP MariaDB target `127.0.0.1:3307/toyjoy_client_feedback_20260819`. The local QA baseline was seeded only after confirming that database initially contained no users, company, branches, stores, or drawers; clearly named `QA-DELETE-*` records were then created through the existing Branch, Store, mapping, and Cash Drawer actions.
- Browser evidence: `/admin/branches` exposed the permission-gated Delete action and its confirmation path; submitting it displayed `تم إرسال حذف الفرع لاعتماد مستقل.`. `/admin/stores` exposed Request archive on both the selling POS location (`MAIN-SALES`) and warehouse (`MAIN-WAREHOUSE`), and `/admin/cash-drawers` exposed Delete. The browser was left open on Cash Drawers. No hard-delete confirmation or approval decision was performed.
- Data-integrity observation: the QA fixture rows (`QA-DELETE-BR-ONLY`, `QA-DELETE-POS`, `QA-DELETE-POS-FREE`, `QA-DELETE-POS-MAPPED`, and `QA-DELETE-DRAWER`) remained active in the named 3307 database. A direct query of that named database immediately afterward found no matching pending approval row, despite the browser success toast; this database/session-target discrepancy is recorded rather than treating the toast as proof of persistence.
- No automated test suite, code change, UAT, Production action, commit, or push occurred. The port-8003 server remains running.

## 2026-08-20 — POS shift drawer-context lazy-loading repair

- Scope: authenticated `GET /pos/shift` rendered active cash-drawer branch/store context while Laravel lazy loading is disabled.
- RED: focused `PosShiftDrawerRelationsTest` against disposable MariaDB `127.0.0.1:3307/toyjoy_client_feedback_20260819` failed as expected with `LazyLoadingViolationException` for `CashDrawer::branch` and HTTP 500.
- GREEN: the focused test passed **1 test / 2 assertions** after eager-loading `branch` and `store` for active drawers. PHP lint passed for the route and test, and scoped `git diff --check` passed.
- Visible browser: authenticated Local System Administrator opened `/pos/shift` at `http://127.0.0.1:8003`; the Arabic page displayed its cash-drawer selector, including `MAIN-01 — ... MAIN → MAIN-SALES`, with no error page. No business data was written, no UAT, Production action, commit, or push occurred.

## 2026-08-20 — P0 forged scope-path focused verification

- Master delete/archive/`openEdit` RED accepted **6 foreign final IDs** and disclosed a foreign cash drawer. GREEN `BranchStoreDrawerMutationScopeTest` passed **7 tests / 31 assertions** on disposable MariaDB `toyjoy_scope_delete_p0_20260820`.
- Sequence foreign create/override was RED, then the focused GREEN passed **4 tests / 8 assertions** on disposable MariaDB `toyjoy_p0_sequence_scope_20260820`.
- The full sequence class result was **10/11**, with an unrelated existing printer-list assertion failure. It is recorded as non-green; no full-class pass is claimed.
- PHP lint, Pint, and `git diff --check` passed for the completed P0 fixes. No browser check, UAT, Production action, commit, or push is claimed by this evidence.

## 2026-08-20 — P0 maker/checker approval-execution verification

- RED: **3 tests / 3 assertions**. An independent approver received `AuthorizationException`; foreign and mismatched scope targets were accepted.
- GREEN: `PlatformMasterApprovalExecutionTest` passed **3 tests / 16 assertions** on disposable MariaDB `toyjoy_approval_execution_20260820` at `127.0.0.1:3307`.
- The repair derives scope canonically from the approval target and permits only approved internal execution; direct actions remain gated and scoped. PHP lint, Pint, and `git diff --check` passed.
- No browser check, UAT, Production action, commit, or push is claimed.

## 2026-08-20 — Purchasing readiness and supplier-return Arabic remediation

- **Scope:** `GET /purchasing/invoices/readiness` and Livewire `/purchasing/returns`, against disposable MariaDB `toyjoy_client_feedback_20260819` at `127.0.0.1:3307`.
- **RED:** `PurchasingArabicUiTest` first failed **2 tests / 4 assertions**: the readiness view discarded its route-supplied decision groups, and the supplier-return empty state rendered the mixed string `لا المورد returns yet.`. A title-only assertion was intentionally removed because the existing Livewire title already rendered Arabic with whitespace around the tag.
- **GREEN:** focused `PurchasingArabicUiTest` passed **2 tests / 6 assertions** after rendering the existing readiness decision/blocker data, correcting the related Arabic translation keys, and preferring Arabic product, supplier, and reason labels in Arabic locale. PHP lint passed for both affected Blade files, both JSON catalogs parsed with PowerShell `ConvertFrom-Json -AsHashTable`, and `git diff --check` passed (only pre-existing CRLF normalization warnings).
- **Browser boundary:** Browser Use initially received `ERR_CONNECTION_REFUSED`; after local-server recovery, the in-app browser session was on its generated error page and its URL policy blocked retry navigation. No fresh headed browser pass is claimed. No migration, business-data write, UAT, Production action, commit, or push occurred.
