# Module 1 localization and settings-master result

Date: 2026-10-07
Worktree: `C:\Users\N\.codex\worktrees\m1-localization\erp`
Branch: `hotfix/module1-localization-20261007`
Baseline: `a01af760a2102dae4993aaa47a47e7c6847a11f3`

## BUG-225 — nested validation field labels

**Classification:** Confirmed local defect; repaired in the scoped Livewire flows.

Before the repair, the create-user password message included `userForm.password` and the settings tax-rate message included `taxSettingForm.rate`. The Arabic rule templates were already present in both `lang/ar/validation.php` and `lang/ar-EG/validation.php`; the missing piece was a friendly attribute name for each nested Livewire field. The test explicitly sets the app locale before making the Livewire request, so the Arabic result exercises the Arabic rule group rather than relying on the session alone.

Added Livewire `validationAttributes()` mappings for `userForm.password` and `taxSettingForm.rate`, with the password label selecting the optional wording while editing an existing user. No validation rule or message template was changed by this finding.

The focused test now asserts these full rendered messages:

| Locale | User password | Tax rate |
|---|---|---|
| English | `The Password field must be at least 8 characters.` | `The Tax Rate (%) field must be at least 0.` |
| Arabic / Egyptian Arabic | `طول كلمة المرور لازم يكون على الأقل 8 حروف.` | `الـ نسبة الضريبة (%) لازم يكون على الأقل 0.` |

The edit-user path also verifies `The New password (optional) field must be at least 8 characters.`. The test asserts the nested property names no longer appear in the Livewire response.

## M1-F19 — all-master locale metadata proof

**Source PDF status:** `PARTIAL` remains unchanged. The source report only established Arabic direction on the branch route and said broader bilingual coverage was incomplete.

The bounded local matrix test exercised 11 UI-ADM-002–012 routes in `en`, `ar`, and `ar-EG` (33 rendered HTTP responses). It asserts the exact page title, `lang` and `dir` attributes, and the selected settings panel for the six Settings tab routes. It found two missing exact title keys: `Branch Management` and `Role Permissions`. Added those keys only to `lang/ar.json` and `lang/ar-EG.json`.

The test expectation for UI-ADM-005 follows the configured English dictionary value (`Cashier Tills`); the source title is `Cash Drawer Masters`, and the existing Arabic title is `خزائن الكاشير`. This was an expectation correction, not a production defect.

This matrix verifies rendered route metadata and settings panel selection. It does not claim that every label, message, UI state, or visual RTL/LTR placement was reviewed. No interactive browser check was run.

## M1-F18 and separate local finding M1-EX03 — Settings master lists

**M1-F18 source PDF status:** `PARTIAL` remains unchanged; the PDF said search/pagination and large data were not stressed.
**M1-EX03 classification:** Newly confirmed local defect, reported separately from the original 31 matrix entries.

The local payment-method regression reproduced the reachability issue: the inline Settings view fetched at most 100 rows and the 101st seeded record was absent. Source inspection found the same `limit(100)->get()` pattern on the payment, tax, document-numbering, and configured-printer master lists, while UI-ADM-006–009 require search and paging. Parent approval was received before expanding the repair to all four lists.

Replaced each active master-table query with native Livewire pagination (25 rows per page), a shared `x-tables.filter-bar` search control, and the shared `x-tables.pagination` component. The one `masterListSearch` value and default page reset on search and tab changes. Search covers code and Arabic/English names for payment/tax rows; document type, scope, prefix/suffix and branch labels for sequences; and printer identity/type/paper/template plus visible branch/store/template labels for printers. Existing company/location scopes are applied before search and pagination; the paginated table rows are not used as modal selection options. Existing eager-loaded sequence branches and printer relations remain eager-loaded.

The focused scale test creates 101 rows for each list and proves that page one reports 25 of 101 and omits row 101, page five exposes row 101, and a search for that row returns one result. It also proves the master-list query does not read the three inactive list tables while Payments is selected, search/page state resets on tab changes, a restricted actor sees only its authorized company/branch tax/sequence/printer records, and all four table result pages are bounded.

No N+1 or broad performance claim is made; query-count assertions are limited to inactive master-list reads. The payment model has no tenant scope in the current schema, while tax uses `forCompany()` and sequence/printer queries retain their existing `visibleTo()` scopes.

## Verification

Disposable database only: `qa_rajh_m1_localization_20261007` on loopback XAMPP MariaDB. The schema was freshly migrated using the guarded harness. No application SQLite, normal local schema, production database, or `.env` file was used.

Focused command: `php vendor/bin/phpunit tests/Feature/Module1ValidationLocalizationTest.php tests/Feature/Module1MasterLocalizationTest.php tests/Feature/Module1MasterScaleTest.php --no-progress` with the guarded schema-specific QA environment.

Result after formatting: **12 tests passed, 235 assertions passed**. The test set covers the observed RED cases, bilingual field labels and complete validation sentences, the 33-response title/direction matrix, the four 101-row master lists, scope protection, reset behavior, and inactive-list query exclusion.

Installed Pint was run using the regression worktree's bundled binary against both changed SFCs and all three focused test files. Pint applied its `no_alternative_syntax` and related formatting fixers to the full Settings SFC. Final review removed a redundant active printer query that fetched up to 100 rows before replacing the result with the paginated query; the remaining `limit(100)` is in an existing `@if(false)` print-assignment block and was left unchanged. After formatting and that removal, the focused suite passed and `git diff --check` passed. No full suite, interactive browser check, push, deployment, or merge occurred; the implementation is committed locally and awaits lead integration review.

## Remaining boundary

M1-F18 and M1-F19 retain the source PDF's `PARTIAL` statuses; these local checks cover only the named Settings lists and page metadata. Final integration with the separate tax-rate rule and other agents' locale/message edits remains the lead's responsibility, followed by the requested integrated regression pass.
