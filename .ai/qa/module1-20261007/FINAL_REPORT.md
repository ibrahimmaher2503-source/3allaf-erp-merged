# Module 1 final QA report — 2026-10-07

**Local QA closure: all ten second-pass reviews and accepted fixes are integrated. Combined validation completed; the full suite and static check remain failed with the documented debt below.**

## A. Executive result

All 20 PDF pages were read once, including evidence images. Original scope: 9 reported bugs (4 medium, 5 low), 22 non-BUG findings, 18 preserved PASS observations. Seven reported bugs were locally fixed and verified; BUG-221 and BUG-227 did not reproduce against the current local implementation. Production was not inspected or changed. Exactly ten Luna x-high agents completed independent first-pass scopes and completed the requested second-pass review. No business milestone, client UAT or production release is approved by these results.

Source: `e693ab8c2e86b6e4e8ef628aacef2aa3d8ae987e`; Module 1 integration branch: `hotfix/module1-qa-20261007`. Module 2 was subsequently authorized as a separate batch from frozen `07a514c`; its pushes do not authorize main merge or deployment.

| Disposition | Original findings |
|---|---|
| NOT REPRODUCED | 8 |
| FIXED + VERIFIED | 8 |
| AMBIGUOUS REQUIREMENT | 6 |
| BLOCKED | 4 |
| NOT FIXED | 1 |
| OUT OF SCOPE | 2 |
| FIXED BUT NOT FULLY VERIFIED | 2 |

Statuses describe the specific local finding only. BLOCKED mail/device/remote items are not passing checks. NOT FIXED for F10 identifies incomplete coverage, not a proved runtime exploit.

## B. Reported bugs

### BUG-221 — MEDIUM — NOT REPRODUCED

Root cause / fix: Existing server limiter rejects the sixth invalid login; PDF screenshot has an empty password and does not establish seven HTTP login attempts.

Actual evidence: AuthenticationRegressionTest: 3/35; six actual non-empty browser attempts on isolated local server, sixth 429.

Risk / limit: No limiter change; remote result remains unverified.

### BUG-222 — MEDIUM — FIXED + VERIFIED

Root cause / fix: Inbox omitted canonical submitted before/proposed context. Added source-aware tax allowlist and branch dependency counts, preserving redaction and scope.

Actual evidence: ApprovalInboxPresentationTest: 5/37, including self-decision/scope denial and unchanged source hash.

Risk / limit: Allowlist must be updated deliberately for new setting fields; counts are request-time context.

### BUG-223 — MEDIUM — FIXED + VERIFIED

Root cause / fix: Blank/non-numeric tax rate reached request/persistence paths. Shared required numeric 0–100 validation runs before side effects; explicit zero remains valid.

Actual evidence: Module1TaxRateValidationTest: 9/108; rejected approval effects roll back.

Risk / limit: Historical tax snapshots unchanged; full historical receipt reprint was source-reviewed, not dynamically tested.

### BUG-224 — LOW — FIXED + VERIFIED

Root cause / fix: Generic lifecycle error and missing review context. Parent-first branch locks, typed dependency validation, request-time counts and approval-time locked child recheck.

Actual evidence: BranchLifecycleDependencyTest: 5/25; two-worker branch/store and approval/hash checks: 4/25.

Risk / limit: Active user scopes are context, not newly invented blockers; logical deletion never cascades.

### BUG-225 — LOW — FIXED + VERIFIED

Root cause / fix: Nested Livewire field names lacked validation attribute mappings. Added native human-readable names using existing locale validation templates.

Actual evidence: Module1ValidationLocalizationTest verifies complete en/ar/ar-EG error sentences; merged tax/localization/branch checks 26/368.

Risk / limit: Does not claim every validation message throughout the ERP is localized.

### BUG-226 — LOW — FIXED + VERIFIED

Root cause / fix: Inactive accounts were rejected at login/gates but established sessions survived. Web middleware now logs out, invalidates session and rotates CSRF on the next request.

Actual evidence: AuthenticationRegressionTest: 3/35; second-pass HTTP and actual Livewire revocation checks.

Risk / limit: Financial shift, drawer assignment and active-shift records are preserved; owner must decide emergency recovery.

### BUG-227 — LOW — NOT REPRODUCED

Root cause / fix: Current canonical cashier has approved grants and can GET/open a real shift. Remote Cashir alias is not the local canonical profile.

Actual evidence: PosShiftOpenRouteTest: 5/48 with seeded canonical role, real POST and scope/exclusivity denials.

Risk / limit: No role/grant/alias change; production role data was not inspected.

### BUG-228 — MEDIUM — FIXED + VERIFIED

Root cause / fix: Row locks serialized writes without identifying stale forms; timestamps have second precision. Added monotonic lock_version, locked UI baseline, atomic comparison/increment for save/status/delete.

Actual evidence: CashDrawerConcurrencyTest: 8/57, including same-second stale edit, actual two-worker winner, forged baseline, retained dirty modal and approval delete.

Risk / limit: Additive migration applied locally; every future deployment needs the migration through the established release workflow.

### BUG-229 — LOW — FIXED + VERIFIED

Root cause / fix: Numbering and inventory draft writers omitted known branch scope; presentation maps were incomplete. Fixed writer attribution and reviewed labels; expose only safe truncated fingerprints.

Actual evidence: Module1AuditPresentationTest: 4/31; branch/store visibility, cross-branch exclusion, raw metadata absence and fail-closed export.

Risk / limit: Existing historical unscoped events are preserved; no data backfill or export permission expansion.

## C. Coverage and owner decisions

| ID | Status | Evidence / disposition | Remaining limit |
|---|---|---|---|
| M1-F01 | AMBIGUOUS REQUIREMENT | Public self-registration policy is not established by the routed approved requirements. Existing Fortify registration remains unchanged. | Owner must decide whether public registration is allowed. |
| M1-F02 | NOT REPRODUCED | Approved inventory exception policy explicitly requires negative stock blocked by default; Blocked (Safe) reflects that behavior. docs/25-inventory-exception-policy.md sections 1/3 and existing UI mapping reviewed. | No policy change needed. |
| M1-F03 | AMBIGUOUS REQUIREMENT | Warehouse count deliberately selects warehouse-type stores, excluding selling outlets; terminology decision remains open. Current query and DEC-021 reviewed. | Owner decides taxonomy/labels, not an inferred count defect. |
| M1-F04 | AMBIGUOUS REQUIREMENT | Canonical role baseline and remote aliases cannot be reconciled by inventing grants. Module1AuthorizationBoundaryTest: 9/42, including canonical guard and forged flag denial. | Remote aliases/counts unverified; no existing grants changed. |
| M1-F05 | AMBIGUOUS REQUIREMENT | Existing maker-checker behavior is enforced; business assignment of independent reviewers remains unspecified. Approval presentation plus same-source approval concurrency and hash rollback checks. | Preserved explicitly approved immutable SuperAdmin bypass; no new role grants. |
| M1-F06 | BLOCKED | Remote Super Admin account claim requires authorized remote data access; local system-admin role and immutable SuperAdmin flag are distinct. Local creation of a second role-based administrator succeeds; role/flag protections verified. | No remote access or singleton rule invented. |
| M1-F07 | AMBIGUOUS REQUIREMENT | Positive audit export row cap needs owner configuration; fail-closed behavior is intentional. Export rejects null/unconfigured cap and does not write success audit. | Owner chooses a positive cap before enabling export. |
| M1-F08 | AMBIGUOUS REQUIREMENT | Current approved printer contract permits nullable template; report does not establish mandatory assignment. Printer save contract reviewed; field remains nullable. | Physical print acceptance is separate. |
| M1-F09 | NOT REPRODUCED | No numbering corruption found in actual overlapping allocations. DocumentSequenceConcurrencyTest: real two-process lock contention, unique consecutive numbers and counter=3 after two allocations. | Specific schedules are verified, not exhaustive load stress. |
| M1-F10 | NOT FIXED | Bounded security matrix was expanded but complete role-by-action-by-record matrix was not executed. Real route/action/scope/export/last-admin/mass-assignment checks; no blanket Gate override claimed as security evidence. | Global-only audit route versus separately scoped export policy still needs role policy clarification. |
| M1-F11 | OUT OF SCOPE | No upload surface exists in the Module 1 scope. Other modules have upload workflows; Module 1 does not. | Does not claim all application uploads absent or verified. |
| M1-F12 | BLOCKED | Physical device/offline PWA acceptance needs devices and approved connectivity scenarios. Static network/offline safety reviewed only. | Offline, printer, scanner and reconnect UAT remain unexecuted. |
| M1-F13 | BLOCKED | App reset dispatch/token/reset were verified with fake notifications; actual SMTP delivery requires external mail configuration. AuthenticationRegressionTest proves token flow and changed password. | No claim of delivered mail. |
| M1-F14 | NOT REPRODUCED | Local last-administrator removal/deactivation protection holds. Module1AuthorizationBoundaryTest includes successful second admin and rejection of final-role removal/deactivation. | Concurrent final-admin mutations were not exercised. |
| M1-F15 | NOT REPRODUCED | Company information is editable through the approved authorized editor; read-only observation did not reproduce. Company success/readback plus denial/invalid-input checks in tax/settings focused class. | Remote role policy remains unverified. |
| M1-F16 | NOT REPRODUCED | Payment method lifecycle works for the bounded scenarios. Authorized valid save/display and denied/offline-card invalid zero-effects checks. | Does not prove external payment services. |
| M1-F17 | BLOCKED | Physical test-print/transaction-output acceptance needs actual hardware. No physical print check performed. | Printer and required paper-format UAT remain open. |
| M1-F18 | FIXED BUT NOT FULLY VERIFIED | Four Settings master lists capped at 100 were unreachable above that limit. Added native scoped 25-row pagination/search, active-tab queries and page reset. 101-row reachability, search/scope/reset and inactive-list exclusion checks; actual Arabic desktop/mobile payment search. | Large-volume stress and every reference selector remain unmeasured. |
| M1-F19 | FIXED BUT NOT FULLY VERIFIED | Added missing Arabic Branch Management/Role Permissions keys and named field errors. 11 pages × 3 locales bounded HTTP render proof; actual Arabic RTL desktop/mobile settings with no page overflow. | All text/states not covered; System Settings breadcrumb remained English in the observed Arabic page. |
| M1-F20 | NOT REPRODUCED | Editing numbering metadata preserves an allocated counter and direct counter rewind is rejected. DocumentSequenceConcurrencyTest metadata scenario; canonical permission fixture preserved when pre-seeded. | Approved/stale override rollback not dynamically covered in this pass. |
| M1-F21 | FIXED + VERIFIED | Stored hashed device/session context existed but was not visible. UI now displays only 12 hex chars of valid SHA256 hashes. Actual request audit plus Livewire render checks exclude raw IDs, complete hashes and user agent. | No arbitrary metadata rendered. |
| M1-F22 | OUT OF SCOPE | Remote QA records, pending approvals and orphaned shift were intentionally preserved. No production/remote cleanup or financial auto-close performed. | Cleanup requires a separate owner-authorized operational task. |

Derived findings are separate from the original 31; all ten review reports are preserved in `redteam/`.

| ID | Status | Change / limit | Actual evidence |
|---|---|---|---|
| M1-EX01 | AMBIGUOUS REQUIREMENT | Default assigned cashier versus exclusive drawer ownership; no new ownership policy. | No policy change |
| M1-EX02 | FIXED + VERIFIED | OpenShiftAction rechecks locked active selling store before creating financial state. | PosShiftOpenRouteTest 5/48 |
| M1-EX03 | FIXED BUT NOT FULLY VERIFIED | Native scoped Settings pagination/search; F18 contains remaining scale limits. | 101-row reachability; actual Arabic desktop/mobile filter |
| M1-EX04 | AMBIGUOUS REQUIREMENT | Changing selling-store type during an existing open shift needs owner policy. | No policy change |
| M1-EX05 | FIXED + VERIFIED | Drawer reactivation rechecks active selling-store invariant under parent locks. | CashDrawerConcurrencyTest 9/63 |
| M1-EX06 | FIXED + VERIFIED | Inventory approval audit records the known store branch; visibility is preserved. | ApproveInventoryAdjustmentActionTest 1/4; mixed review 34/213 |
| M1-EX07 | FIXED + VERIFIED | Custom Fortify callback uses native configured minimum Timebox for failed credentials. | AuthenticationRedTeamReviewTest 6/65; no timing benchmark; overflow can exceed minimum |
| M1-EX08 | FIXED + VERIFIED | Approval decision failures use translated English source keys in en/ar. | Module1RedTeamLocalizationTest 2/25 |
| M1-EX09 | FIXED + VERIFIED | Print-template editor reauthorizes after permission revocation. | Actual stale Livewire snapshot denied; mixed 18/114 |
| M1-EX10 | FIXED + VERIFIED | Native Locked company context blocks client-forged cross-company tax lists. | Settings/approval authorization red-team 9/46 |
| M1-EX11 | FIXED + VERIFIED | Shift opening locks current user and rereads current scope after locks, preventing revoked-scope race. | Real two-worker RED then GREEN 1/5; POS 5/48 |

## D. Verification actually run

- Lead first-pass local runs: auth 3/35; approval 5/37; drawer 8/57; tax/audit/POS/authorization combined 27/229; numbering+harness 4/41; merged localization/tax/branch overlap 26/368. These runs overlap and must not be summed as unique test counts.
- Initial full baseline: 152 tests, 110 passed, 29 failures, 13 errors, 5,110 assertions. Most failures are historical source-text contracts; validator boot/order and native Windows cache-path errors were corrected in the shared harness. Arabic PDF still requires trusted FriBidi.
- Initial static baseline: 1,404 reported PHPStan findings in 255 files, captured before implementation. No broad suppression/baseline changes.
- Vite build passed once (3.66 s). Composer validate passed with the existing exact OpenSpout version warning. Initial modified PHP syntax and JSON checks passed.
- Required Pint attempted in the root; copied PHAR embeds a different vendor path. The same already installed working regression-worktree launcher formatted affected files; no dependencies/vendor edits.
- Actual authenticated local Arabic desktop/mobile payment search: CASH filters to one row, CARD excluded, RTL/lang correct, 390px viewport had no document overflow. No persisted browser business write. Boost browser logs unavailable because the file was absent.
- Final combined run on `fix/module2-qa-integration`: **264 tests, 228 passed, 34 failures, 2 errors, 6,257 assertions**, exit 2. Every new reported-fix test passed. No second full-suite run.
- Of the final failing test names, two newly fail legacy source-string contracts in `V0122Hotfix13CashDrawerIntegrityTest`: they require the old drawer lock expression and ordinary post-lock visibleTo query. The accepted current-lock/scope repair replaces those expressions; actual supported-route and two-worker scope-revocation behavior passed. These tests were preserved, not weakened. Four baseline errors now reach failing historical assertions after the harness correction; the other failing names were already failing/erroring in the original baseline.
- Final static capture: **1,403 findings versus 1,404 baseline; zero added diagnostics, one removed**, comparing file/message/identifier rather than line offsets. Initial final capture was truncated; the cached verbose capture completed the comparison. No suppression or dependency change.
- Final optimize:clear, integrated Vite build and separate temporary Blade compilation passed. Syntax passed for 69 changed PHP files; three locales parse with zero new duplicate keys after removing one accidentally repeated ar-EG audit key. Pint fallback passed; its five-file formatting delta was inspected and committed separately.
- Final remote read verified all seven Module 2 branch tips. Integration closure commit `3e13a57` was published to the exact `fix/module2-qa-integration` branch; the factual receipt is a documentation-only follow-up. No main merge or deployment.

Only recorded disposable schemas `qa_rajh_m1_<domain>_20261007` and `qa_rajh_m1_integrated_20261007` on XAMPP MariaDB were used for tests. The additive drawer migration was also applied to the normal local database after confirming its identity; no data reset, production migration or real-record deletion occurred.

### Full-suite failed cases

- `Tests\Unit\CompanyOwnerRbacApprovalNavigationTest::test_administration_activity_and_approval_navigation_have_exact_guards_and_placement`
- `Tests\Unit\M52ProductCardsOpeningInventoryTest::test_opening_inventory_template_has_exact_three_column_order`
- `Tests\Unit\M52ProductCardsOpeningInventoryTest::test_opening_validation_rejects_service_missing_cost_existing_movement_and_fraction_policy`
- `Tests\Unit\M52ProductCardsOpeningInventoryTest::test_opening_reversal_and_zero_decision_are_audited_and_setup_readiness_is_authoritative`
- `Tests\Unit\M54PurchaseReceivingDistributionTest::test_full_distribution_is_mandatory_and_rejects_invalid_allocations`
- `Tests\Unit\M54PurchaseReceivingDistributionTest::test_full_page_two_stage_ui_scanning_and_destination_labels_are_present`
- `Tests\Unit\M54PurchaseReceivingDistributionTest::test_m54_flux_references_are_supported`
- `Tests\Unit\V0122Hotfix16PosCheckoutWorkflowTest::test_customer_and_payment_ux_is_bounded_responsive_and_safe`
- `Tests\Unit\V0122Hotfix16PosCheckoutWorkflowTest::test_success_receipt_routes_and_locale_parity_are_complete`
- `Tests\Unit\V0122Hotfix2CorrectiveTest::test_product_card_normalizes_unchecked_quantity_and_persists_canonical_price`
- `Tests\Unit\V0122Hotfix4InventoryLabelsSidebarPricingTest::test_product_quantity_is_central_strict_and_integer_only`
- `Tests\Unit\V0122Hotfix6AdminNavigationLocalizationTest::test_sidebar_derives_nested_authorized_links_from_the_lightweight_registry`
- `Tests\Unit\V0122Hotfix6AdminNavigationLocalizationTest::test_warehouse_terminology_and_store_action_are_localized`
- `Tests\Unit\V0122Hotfix7InventoryCountingUxTest::test_human_names_navigation_and_bounded_search_are_present`
- `Tests\Unit\V0122Hotfix9NavigationCleanupTest::test_navigation_hides_only_the_requested_duplicate_and_deferred_entries`
- `Tests\Unit\V016SetupAdminFoundationTest::test_authoritative_group_map_contains_exactly_twenty_one_unique_steps`
- `Tests\Unit\V021FinalAcceptanceTest::test_scanner_and_bounded_search_contract`
- `Tests\Unit\V021PurchasingLocalizationLayoutHotfixTest::test_every_purchase_invoice_literal_has_an_arabic_translation`
- `Tests\Unit\V021PurchasingLocalizationLayoutHotfixTest::test_list_and_entry_layout_contracts_are_responsive_and_locale_safe`
- `Tests\Unit\V021PurchasingLocalizationLayoutHotfixTest::test_status_and_currency_presenters_never_leak_raw_values`
- `Tests\Unit\V021ReadinessPricingHotfixTest::test_readiness_ui_localization_prerequisite_and_list_zero_counts_are_explicit`
- `Tests\Unit\V021ReadinessPricingHotfixTest::test_all_post_deployment_corrections_remain_in_source`
- `Tests\Unit\V022CumulativeStabilizationTest::test_template_preview_is_bilingual_responsive_and_uses_no_business_data`
- `Tests\Unit\V022Hotfix1PostDeploymentTest::test_quantity_contract_is_integer_and_diagnostic_is_read_only`
- `Tests\Unit\V022Hotfix1PostDeploymentTest::test_setup_filters_labels_localization_and_version_contracts`
- `Tests\Unit\V022Hotfix1PostDeploymentTest::test_pricing_remediation_and_print_layout_are_explicit`
- `Tests\Unit\V022RequirementFamiliesTest::test_setup_order_actions_and_persistent_sequence_family`
- `Tests\Unit\V022RequirementFamiliesTest::test_purchasing_distribution_transfer_label_and_transit_family`
- `Tests\Feature\V0122Hotfix12PosShiftOpenRouteTest::test_shift_open_flow_uses_separate_safe_routes_and_preserves_authorization`
- `Tests\Feature\V0122Hotfix13CashDrawerIntegrityTest::test_valid_drawer_opening_keeps_transaction_scope_and_duplicate_guards`
- `Tests\Feature\V0122Hotfix13CashDrawerIntegrityTest::test_invalid_or_cross_scope_drawers_are_not_selectable_and_return_safe_validation`
- `Tests\Feature\V0122Hotfix27PurchaseOrderLookupTest::test_purchasing_lookup_filters_incrementally_and_uses_only_latest_approved_supplier_price`
- `Tests\Feature\V0122Hotfix27PurchaseOrderLookupTest::test_supplier_return_lookup_is_source_invoice_and_store_scope_constrained`
- `Tests\Feature\V0122Hotfix27PurchaseOrderLookupTest::test_authorized_create_route_renders_full_page_and_shared_action_saves_a_draft`

### Full-suite environment/file errors

- `Tests\Unit\M51CustomerSupplierDataTest::test_arabic_pdf_text_is_shaped_in_visual_order_without_changing_stable_codes` — RuntimeException: The trusted local FriBidi renderer is required for Arabic PDF output.
- `Tests\Unit\M54PurchaseReceivingDistributionTest::test_six_stabilization_render_targets_and_setup_context_are_wired` — TypeError: Tests\Unit\M54PurchaseReceivingDistributionTest::source(): Return value must be of type string, false returned

## E. Changed files and purpose

Module 1 implementation inventory through `b4d2e54`, relative to the recorded source; final combined check metadata, the CashDrawer test formatter delta and duplicate locale-key cleanup are recorded above; final report/matrix and copied ten review reports are additional evidence-only files.

| File | Purpose |
|---|---|
| `.ai/CURRENT_TASK.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/DECISIONS.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/SESSION_SUMMARY.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/TEST_RESULTS.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/PLAN.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/approvals-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/audit-redteam-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/audit-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/auth-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/authorization-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/branches-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/concurrency-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/localization-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/matrix.json` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/pos-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/regression-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `.ai/qa/module1-20261007/tax-result.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `AI_INDEX.md` | Owner authorization, original finding traceability, actual evidence and factual session history. |
| `app/Http/Middleware/RevokeInactiveUserSession.php` | Revoke established inactive-user web sessions at the next request. |
| `app/Modules/Inventory/Actions/ApproveInventoryAdjustmentAction.php` | Known branch audit attribution, approved presentation mappings and safe truncated fingerprints. |
| `app/Modules/Inventory/Actions/SaveInventoryAdjustmentAction.php` | Known branch audit attribution, approved presentation mappings and safe truncated fingerprints. |
| `app/Modules/Platform/Actions/AllocateDocumentNumber.php` | Known branch audit attribution, approved presentation mappings and safe truncated fingerprints. |
| `app/Modules/Platform/Actions/OverrideDocumentSequenceCounter.php` | Known branch audit attribution, approved presentation mappings and safe truncated fingerprints. |
| `app/Modules/Platform/Actions/PlatformSettingsApprovalAction.php` | Shared required numeric tax validation before request/persistence and locked approval effects. |
| `app/Modules/Platform/Actions/SaveBranchAction.php` | Parent-first lifecycle locks and explicit dependency blockers. |
| `app/Modules/Platform/Actions/SaveCashDrawerAction.php` | Atomic stale-version guards and active selling-store reactivation invariant. |
| `app/Modules/Platform/Actions/SaveLocalSettingsAction.php` | Shared required numeric tax validation before request/persistence and locked approval effects. |
| `app/Modules/Platform/Models/CashDrawer.php` | Additive stale-edit version; no financial data reset. |
| `app/Modules/Platform/Support/ApprovalPresentation.php` | Allowlisted before/proposed review context and localized safe decision errors. |
| `app/Modules/Platform/Support/AuditLogPresentation.php` | Known branch audit attribution, approved presentation mappings and safe truncated fingerprints. |
| `app/Modules/Retail/Actions/OpenShiftAction.php` | Locked active selling-store and current scope checks before financial state writes. |
| `app/Providers/FortifyServiceProvider.php` | Native configured authentication failure Timebox. |
| `bootstrap/app.php` | Revoke established inactive-user web sessions at the next request. |
| `database/migrations/2026_10_07_023742_add_lock_version_to_cash_drawers_table.php` | Additive stale-edit version; no financial data reset. |
| `database/seeders/RemediationSeeder.php` | Known branch audit attribution, approved presentation mappings and safe truncated fingerprints. |
| `lang/ar-EG.json` | Reviewed bilingual labels and validation/decision field names. |
| `lang/ar.json` | Reviewed bilingual labels and validation/decision field names. |
| `lang/en.json` | Reviewed bilingual labels and validation/decision field names. |
| `phpunit.xml.dist` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `resources/views/platform/admin/authorization-baseline.blade.php` | Human-readable password validation attributes and reviewed labels. |
| `resources/views/platform/admin/branches.blade.php` | Parent-first lifecycle locks and explicit dependency blockers. |
| `resources/views/platform/admin/drawers.blade.php` | Atomic stale-version guards and active selling-store reactivation invariant. |
| `resources/views/platform/admin/settings.blade.php` | Scoped bounded master paging/search, Locked company context, reauthorized template editing and field labels. |
| `resources/views/platform/system/approval-inbox.blade.php` | Allowlisted before/proposed review context and localized safe decision errors. |
| `resources/views/platform/system/audit-log.blade.php` | Known branch audit attribution, approved presentation mappings and safe truncated fingerprints. |
| `tests/Feature/BranchLifecycleDependencyTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/CashDrawerConcurrencyTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Module1AuditPresentationTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Module1AuthorizationBoundaryTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Module1MasterLocalizationTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Module1MasterScaleTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Module1RedTeamLocalizationTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Module1SettingsAuthorizationRedteamTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Module1TaxRateValidationTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Module1ValidationLocalizationTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/OpenShiftScopeRevocationConcurrencyTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Platform/ApprovalInboxPresentationTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Platform/PlatformSettingsApprovalRedTeamTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/Platform/SettingsSearchScopeRedTeamTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/PosShiftOpenRouteTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/ApproveInventoryAdjustmentActionTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/AuthenticationRedTeamReviewTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/AuthenticationRegressionTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/BranchLifecycleConcurrencyTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/CriticalRegressionTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/DocumentSequenceConcurrencyTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/FinancialBoundaryTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/FinancialContractTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/FinancialMatrixTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/FinancialPrecisionClosureTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/LaravelHarnessSmokeTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/NativeLaravelBootstrapSmokeTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/PlatformSettingsApprovalConcurrencyTest.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/PureCalculatorTestCase.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/Feature/QA/bootstrap.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/TestCase.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |
| `tests/bootstrap.php` | Focused boundary/concurrency/localization evidence or safe native Windows/MySQL test boot; calculator expectations preserved. |

## F. Actual limits

No production runtime/remote data verification, external SMTP delivery, physical scanner/printer, offline recovery, populated trading UAT, full role×action×record matrix, arbitrary third-party attachments, or exhaustive load/concurrency stress was performed. Audit-export cap, public registration, canonical remote aliases, approver role mapping, warehouse terminology, nullable printer template and open-shift recovery remain owner decisions. The initial full-suite/static debt is visible and must not be represented as a green release gate.

## G. Manual retest checklist

Use a disposable local company and authorized roles; retain an independent reviewer. These remaining steps are a checklist, not executed evidence.

- BUG-221: submit six real non-empty wrong-password logins; fifth credentials error, sixth 429 and retry duration.
- BUG-222/223: submit valid zero/nonzero tax changes, inspect redacted before/after; blank/non-numeric/out-of-range values create no request; independent approval applies exact data and rejected effects roll back.
- BUG-224: active-store and active-mapping-only branches show specific blockers; deletion request shows counts; activate a child after requesting deletion and confirm approval blocks without cascade.
- BUG-225: trigger password and tax errors in en/ar/ar-EG; inspect complete field names/messages.
- BUG-226: disable an already logged-in cashier, next normal/Livewire request returns login, session/CSRF rotates; verify open shift/assignment remains for controlled recovery.
- BUG-227/M1-EX02: canonical cashier opens only an active drawer at an active selling store in scope; inactive/warehouse/out-of-scope direct requests leave no shift/assignment/audit.
- BUG-228: open two forms with one version; save newer then older; older retains input/modal and reports conflict. Repeat stale status/delete and same-second update.
- BUG-229/F21: inspect numbering and inventory draft/approval events with branch-only and store-only users; correct source/label, no cross-branch event or raw device/session metadata.
- F18/19: 101+ master records searchable/paginated with row actions; switch tabs/query resets page; desktop/mobile RTL/LTR and all empty/error/denied/loading states.
- F09/14/20: overlap allocations, preserve counters on metadata edit, deny final-admin removal; extend concurrent final-admin and approved override rollback acceptance separately.
- F12/13/17: actual SMTP receive/reset, scanner/printer formats, offline/resync/device UAT and customer acceptance require their configured environments.
- F22: obtain separate cleanup authorization and approved open-shift recovery policy before touching remote QA records.

The follow-on Module 2 batch is recorded separately in `../module2-20261007/results.json`: six confirmed bugs fixed, BUG-106 not reproduced locally with actual checkbox/save/reload evidence. Arbitrary CSV import remains outside the canonical company XLSX contract. Independent pricing reviewer grants remain owner configuration; no default grants were invented. Both module batches are published on the integration branch, with no main merge or production deployment.
