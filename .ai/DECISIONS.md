# Owner Decisions

## 2026-10-19 — Standalone warehouse transfer and purchasing override

An active warehouse/store may exist and operate with branch_id = NULL. Branch membership is not required for inventory transfers or purchase receiving. This owner instruction supersedes the 2026-09-27 source-branch numbering assumption only for a branchless source. Its transfer numbers use an atomic company-scoped `CENTRAL-NNNNNN` sequence; branch-linked sources retain the existing branch-scoped number and immutable source-branch behavior. The source/destination must still be active, same-company, and within the signed-in user's store scope. No branch is assigned automatically.

## 2026-10-13 — POS cash-rounding hotfix override

- The owner explicitly approved administrator-configured positive cash-rounding denominations, including 0.50 EGP, superseding the Hotfix14 fixed-5-EGP restriction for new sales. The existing versioned 5 EGP value remains effective until an authorized administrator changes it. Denominations must be positive and expressible in the currency's two-decimal payment scale; no denomination is selected implicitly for settlement.
- A new append-only POS rounding enable version permits explicit disablement. When no enable version exists, a stored denomination preserves the legacy enabled behavior; when neither exists, rounding is disabled. An enabled setting with a missing or invalid denomination blocks cash only when adjustment is required. A total divisible by the previously approved 5 EGP policy may settle unchanged during that configuration error; this check never calculates or posts a substitute rounding value. Non-cash settlement is unaffected. Posted sale and payment snapshots remain immutable.

- 2026-09-30 — Hotfix51 product/supplier finalization and deployment boundary: local product codes use one resolver for previews, Product Card finalization, and purchasing-invoice inline creation. A canonical supplier code matching `SUP-####` contributes its four digits; an all-numeric supplier code of at least four digits contributes its first four digits. Leading zeros remain significant. Both formats share the existing prefix-scoped locked sequence and collision rules; existing product codes and separate international barcodes remain unchanged. Product Card `Save` is final-only and may not silently persist a draft; `Save draft` remains explicit, and completing a draft updates the same product. `suppliers.logical_delete` authorizes confirmed physical deletion only when no protected dependency exists; linked suppliers and history remain and use deactivation when appropriate. Ordinary product-form explanation belongs in contextual help while actionable errors remain inline. The owner authorized focused missing checks and deployment through the established backup/recovery process, without real supplier deletion/deactivation, production test records, a broad suite, or unrelated work.

- 2026-09-29 — Hotfix50 permission-presentation and deployment boundary: the owner explicitly authorizes the focused permissions-management UI improvement and production deployment from actual active Hotfix49. The change is presentation and pending form selection only; permission keys, IDs, grants, roles, authorization behavior, protected administrator rules, and the existing Save action remain authoritative. One shared bilingual catalog covers the current 412-permission/34-section inventory. Section `All` operates only on the complete active assignable set for its section and creates no wildcard permission. Focused isolated browser control is authorized for the named ar/ar-EG/en, section-only, mixed-state, and Save/reload scenario; production permission testing and broad suites remain prohibited. Deployment must use established encrypted backup, isolated reconciliation, atomic activation, and recovery.

- 2026-09-27 — Hotfix49 generated Product Card identity override: replace only the Hotfix42 local Product Card code rule with the first four characters of the selected supplier's actual `code`, which must all be digits, followed by `-` and a six-digit serial. Suppliers with the same four-digit prefix share one prefix-scoped row in the existing locked global product-code sequence table. Allocation advances before collision checks against product codes and barcodes, never uses an unlocked `MAX`, and does not modify saved codes, international barcodes, inactive-draft behavior, final-code immutability, or the independent supplier English-name requirement.

- 2026-09-27 — Hotfix48 purchasing, transfer, and System Administrator boundary: new purchase orders use quantity multiplied by purchase cost only; new line discount/tax snapshots are zero while historical saved line snapshots remain unchanged. Supplier suggestions use only the latest approved same-product/same-supplier/current-company invoice cost, otherwise zero.
- 2026-09-27 — The current schema has no main-warehouse marker. The sole eligible active warehouse in the selected company/branch is the only unambiguous automatic default; multiple eligible warehouses require explicit selection and remain editable.
- 2026-09-27 — Purchase invoices retain discount/tax behavior and store purchase cost separately from Base Consumer Price List 0. Draft save does not change product cards or supplier history. Approval alone updates the product last-purchase-cost field, Base selling price, and supplier approved-price history; stock-balance WAC and custom overrides remain separate.
- 2026-09-27 — Stock-transfer numbers are allocated atomically on first save from the source branch's shared six-digit continuous sequence and rendered as `<SOURCE-BRANCH-CODE>-NNNNNN`. Existing numbers remain immutable, cancellation never decrements the sequence, and a saved transfer cannot move to another source branch.
- 2026-09-27 — An active `system-administrator` role grants every active application permission and unrestricted active company/branch/store access, including future records, without ID hardcoding. Undefined permission names remain denied. Approval maker/checker bypass remains restricted to the separate immutable Super Admin flag.

- 2026-09-26 — Hotfix47 no-location landing boundary: authenticated landing is selected by one shared policy. An active global System Administrator who can view user/role management but has no visible active store is sent to the existing Users, Roles & Scopes setup step before company-context readiness is evaluated. The destination retains `auth`, `verified`, and permission enforcement. This does not grant tenant visibility, assign branch/store scope, set `is_super_admin`, change verification, or bypass middleware. Dashboard and initial-setup entry points apply the same decision so incomplete setup cannot redirect back through login.

- 2026-09-26 — Hotfix46 administrator identity and verification boundary: the canonical active System Administrator role is global for the existing user/role management surface, consistent with `canManageGlobalRoles()` and the role matrix, but does not set or inherit `is_super_admin`; operational tenant filtering, approval bypass and maker/checker behavior remain unchanged. Accounts created by the gated admin action are verified inside the transaction without notification and receive a distinct creator/verifier audit. Legacy repair requires an immutable `authorization/create_user` event with a non-null actor; the only demonstrated eligible account was ID 2 / `karim`, created by ID 1.

- 2026-09-16 — Hotfix42 schema and behavior authorization: migration 000115 adds only the eight approved `purchase_order_lines` snapshot columns, backfills line totals from subtotal and zeroes new financial fields without changing any header total or historical tax. Migration 000116 changes only `products.item_code varchar(50)` from NOT NULL to nullable while retaining its unique index and all values. Both downs fail closed and application rollback retains both schemas/data. Incomplete products may persist with a NULL code only as inactive drafts; final local codes allocate once under the existing locked global sequence after supplier selection using the first three English supplier-name letters and the established seven-digit width. Warehouses and sales outlets may coexist in one branch. Deferred C remains unresolved and unimplemented.

- 2026-08-23 — Owner-requested privileged role-permission override: a super-admin or user holding the active `system-administrator` role may manage all active permissions, including sensitive permissions, for every role except the `system-administrator` role itself. Canonical role metadata remains protected, and maker/checker rules remain unchanged.

- 2026-08-26 — Source-handoff repository hygiene: generated/runtime artifacts, environment overrides, secrets, caches, logs, local databases/dumps, temporary exports/backups, and private attachments are excluded from version control while intentional examples, migrations, public source assets, and Laravel placeholder `.gitignore` files remain tracked. Item 36 removed the two tracked browser verification logs and generated PHPUnit compiled-view artifacts without rewriting history; the placeholder files were preserved. The stable source-handoff reference is `v0.1.3-source-handoff-complete`, which will be corrected to the item-35 documentation commit after that commit is created.

- 2026-08-26 — Isolated sanitized release lineage: Release Candidate publication uses `release/v0.1.4-rc1-sanitized`, created by removing only the approved QA/runtime/database path roots from isolated history. The original handoff branch remains unchanged as local recovery history and is not approved for publication. The sanitized lineage has no remotes and passed prohibited-path and high-confidence secret scans. Production deployment, migrations, backup verification, QA/UAT, release approval, and owner production approval remain unclaimed.

- 2026-08-27 — M50A shell architecture: one centralized permission-filtered navigation definition powers the desktop sidebar, mobile drawer, and command search. The global `/dashboard` is an operational cross-module summary; existing route-owned module landing pages remain module workspaces. POS stays a dedicated full-screen archetype under Sales & POS. Arabic uses locally hosted Cairo variable fonts, while locale-derived document direction and logical layout utilities preserve RTL/LTR behavior. M50A adds no route, schema, mutation, or commercial-template dependency.

- 2026-08-27 — M50A.1 visual correction: retain the M50A route/permission architecture, make Sales Overview the Sales & POS landing concept while keeping POS directly reachable, and use Inventory as the first real-data module-dashboard implementation. A centralized module-dashboard specification records ten module contracts for staged M50B delivery. Arabic rendering is accepted only with local WOFF2 response, FontFace readiness, Cairo-first computed styles, and glyph-metric evidence; a CSS family declaration alone is insufficient. Mobile KPI regions are keyboard-focusable horizontal strips, while the global document remains overflow-free.

- 2026-08-27 — M50B.1 internal-page and shell-state boundary: resource lists use shared page headers, GET filter summaries, responsive labeled rows, bounded 20/50/100 pagination, and server-validated sort allowlists. Desktop collapse is Rajeh-owned; only a valid version-2 local preference may select collapsed mode, while missing, legacy, unknown, empty, or corrupt state self-recovers to expanded. Flux remains responsible only for the mobile overlay drawer.

## 2026-08-27 — M50B.2 purchasing landing and payable boundary

- Use the existing authorized `purchasing.orders` route as the module landing/dashboard and order/receiving queue; do not add a route.
- Do not infer supplier due/overdue amounts without an authoritative payable ledger.
- Preserve existing routes, Gates, policies, visible-store/branch scopes, actions, calculations, approvals, inventory mutations, and schema.

## 2026-08-27 — M50B.3 inventory scope and Settings loading boundary

- Use `inventory.index` as the existing module landing route and keep catalog, balances, transfers, counts, adjustments, and barcode labels in one permission-filtered navigation group.
- Display inventory value only from authoritative `stock_balances.total_value` and only when `inventory_stock_card.cost_view` is allowed.
- Treat Flux submit loading and an Alpine idle-disabled state as separate concerns: the Company review button invokes only `previewCompany`, while every Settings save action has an explicit loading target.
- Preserve inventory mutations, costing, barcode allocation, routes, permissions, policies, and schema.


## 2026-08-27 — M50B.4 pricing capability and work-context boundary

- Treat immutable `PriceList`/`PriceVersion`/`PriceLine` records, effective dates, open-price bounds, approval, and POS cart-discount permissions as the authoritative pricing capabilities. Do not present standalone promotions or campaign discounts because no such source-owned model/workflow exists.
- Do not infer margin from unrelated or mixed store costs. Pricing coverage and exception indicators remain authorized-store scoped and read-only.
- The prior header selected the first visible store while most dashboards aggregated all visible stores. The shell now defaults to “All authorized stores,” persists an optional permission-checked store in the existing session context, and applies it to supported Sales, Purchasing, Inventory, and Pricing views. POS continues to use its existing shift/store ownership and is never silently reassigned by the shell.

## 2026-08-27 — M50B.5 Party capability and customer-history boundary

- Use the existing authorized `parties.bookings.index` route as the Party module landing page and keep calendar, invoice/payment, operating-order, and booking-detail workflows on their current routes and action boundaries.
- Treat `PartyBooking.location` and Party invoice service lines as the authoritative current venue/service description. Do not invent package, venue, resource-capacity, deposit, or approval master records that do not exist.
- Reuse the existing Customer identity and expose separately scoped Party history in its profile. Retail sales, Product Wallet, Party Wallet, Party invoices, and Party payments remain distinct ledgers and histories.

## 2026-08-28 — v0.1.13 guided-setup and administration UX boundary

- Keep `InitialSetupStatus` as the readiness authority and derive continuation from the first authorized required incomplete item; visiting a page never marks it complete and no persisted wizard-position field is introduced.
- The canonical hierarchy is `Branch → Store(type=selling) → CashDrawer → PosShift`. Present it as Branch → Sales Outlet → Cashier Till → Shift while preserving every internal class, field, route, permission, action, and relationship.
- Settings navigation wraps existing save methods: navigation happens only after successful validation/persistence, each control targets its exact action, and read-only history has no save action.
- Branch success guidance may prefill only the existing authorized store form. Primary-outlet selection appears only through the existing mapping action and is never fabricated or silently reassigned.


## 2026-08-28 — v0.1.14 navigation, table and action-system boundary

- Server-resolved route patterns are the authority for active module and destination state; local storage controls only the valid expanded/collapsed desktop preference and is migrated to version 3 with an expanded fallback.
- `InitialSetupStatus` remains the sole readiness authority. The primary 21-step context persists on linked pages and the settings-only numbered navigator remains secondary; neither writes progress.
- Management lists share table density, focus, mobile-record, and semantic action presentation. Print/PDF/export documents and the operational POS cart remain specialized and are intentionally excluded.
- Exact `wire:target` values scope confirmed loading states to their actual action. Routes, methods, permissions, policies, schema, calculations, and workflows are unchanged.


## 2026-08-28 — v0.1.15 genuine visual acceptance boundary

- `InitialSetupStatus` remains the sole readiness authority. The UI maps all 22 unique steps, including the existing `opening-configuration` step after approved selling prices; totals remain permission-filtered and derived rather than hard-coded.
- Settings actions retain their original methods and validation. The shared loading button exposes a spinner only for its exact `wire:target`; read-only sections never receive a save action.
- Responsive management tables stop horizontal scrolling when they have transformed into mobile record cards. Desktop/tablet overflow behavior remains available where content genuinely needs it.
- Genuine evidence may read authoritative data only through GET/HEAD requests guarded by a database read-only session and request rollback, with all writable runtime state task-local. No production record mutation is permitted.

## 2026-08-29 — v0.1.16 Setup decision and Cashier boundary

- `InitialSetupStatus` remains the sole readiness calculator; `InitialSetupRouteMap` is the sole ordered group/route ownership map. Product Import is an action inside Product Cards, not a readiness step.
- Setup skip/defer choices are append-only company-scoped records with actor and timestamp. Skipping applies only to optional steps; deferring required work never marks readiness complete and is surfaced after other actionable work.
- Supplier readiness is an active Supplier plus an active preferred financial-settlement Payment Method. Payment terms and supplier-product/SKU references remain optional.
- The built-in Cashier template uses only existing POS, collection/evidence, assigned shift, catalog/stock read, and customer permissions. Existing user branch/store scopes and active till/shift assignment remain the authorization boundary; no user is auto-assigned.

## 2026-08-30 — M52 catalog identity and opening-inventory boundary

- International barcodes remain string identities and use applicable GTIN check digits. Local barcodes are exactly `0` plus the first four numeric supplier-code digits plus a six-digit per-supplier committed sequence, allocated under a database row lock and uniqueness constraint.
- Product cost and base consumer selling price are separate explicit fields; neither is inferred from the other. The shared pricing contract permits changes only from the corresponding approved explicit input.
- Opening Inventory is a dedicated immutable ledger document, not a transfer, purchase invoice, or count. It posts all lines transactionally once; corrections use a referenced reversal. Setup readiness requires an approved non-reversed opening document or the audited company zero-opening decision.

## 2026-08-31 — M53 reusable price-list boundary

- Product Card `sale_price` remains the authoritative Base Price List 0 value and is not duplicated into derived lines. Additional list percentages always calculate from that base and round upward to the next 5 EGP with decimal-scaled integer arithmetic.
- Outlet assignment resolves in the order outlet, branch default, then List 0. Branches remain organizational; only stock-bearing Sales Outlets resolve selling prices.
- Manual product/list overrides are dated, reasoned, company-scoped, and audited. They never overwrite Product Card prices; removing an override restores derived pricing immediately.
- Existing approved price versions/lines and historical invoice/sale snapshots remain immutable. The new resolver/DTO is the contract for later sales, transfer, label, and report integration rather than a rewrite of historical records.

## 2026-09-05 — Hotfix14 pricing and POS cash rounding

- The M53 automatic percentage-derived selling-price rule is confirmed as decimal-safe upward rounding to the next 5 EGP. Base List 0 prices, explicit manual overrides, costs, and historical or posted document snapshots never enter that rounding path.
- POSF-02 is owner-approved at exactly 5 EGP using the existing nearest-denomination algorithm. It applies to cash tender settlement only; electronic and other non-cash-only settlement retains the exact invoice total.
- `pos.cash_rounding_denomination` remains append-only versioned configuration. Hotfix14 appends `5.0000` only when it is not already the effective value. Missing or invalid state disables cash with a localized safe message rather than an unhandled exception; it does not disable a valid non-cash tender.


## 2026-09-05 — Hotfix15 complete-batch boundaries

- The owner-directed Hotfix15 closes all four named scopes together from exact Hotfix14. The UI removals delete presentations only; canonical pages, routes, permissions, underlying readiness logic, and lower sidebar destinations remain.
- Every downloadable import workbook is generated fresh for the authorized company, carries an exact schema/company identity and machine-header contract, and uses a dedicated `Data Entry` worksheet plus current reference worksheets. Opening Inventory pre-fills eligible current products without unit cost; only authorized location and quantity are editable. Altered schemas, company identities, headers, locked references, formulas, or out-of-scope data fail closed or produce explicit row rejections.
- Product/location assignment is configuration only and never creates stock. Opening Inventory remains a separate company/location-scoped immutable document: Product Card cost is authoritative, approval posts the ledger once under a transaction, and correction uses a referenced reversal.
- Because Product is currently a global master without a company ownership column, permanent removal is allowed only when the centralized dependency scan finds no operational/history dependency and exclusive company scope is provable. Any operational/history dependency forces archive; ambiguous multi-company ownership fails closed for central review. Direct model deletion is blocked outside the guarded removal action.
- QA/UAT/demo product discovery and cleanup are separate root-only commands. The audit is read-only; cleanup requires explicit audited IDs, an exact confirmation token, a fresh dependency/scope check inside the transaction, and a pre-cleanup production backup. Neither command is part of release activation and neither was run during candidate preparation.

## 2026-09-06 — Hotfix16 POS open-order, settlement, and receipt boundary

- Open POS orders are durable server records and remain drafts until the existing `RetailSaleAction` completes payment and posting. Their authorization identity is the exact company, branch, selling outlet, cash drawer, shift, cashier, and stable checkout token; no cross-scope fallback is permitted.
- Existing price, tax, approval, cash-rounding, shift, drawer, inventory, accounting, loyalty, audit, numbering, payment evidence, gift-card, idempotency, and immutable sale-snapshot services remain authoritative. Hotfix16 coordinates them and does not reimplement or weaken them.
- Electronic portions are explicit positive allocations. At most one cash portion is allowed and it settles only the residual after electronic portions; change derives only from cash tendered. Safe references reject likely PAN/security data and no PAN, CVV, track data, or PIN field exists.
- Receipt preview requires an approved visible sale and an active printer/template assignment matching the sale company, branch/outlet eligibility, sales-invoice document type, paper size, and locale or bilingual setting. The UI always exposes a localized browser-print fallback rather than silently routing to an unrelated printer.
- The owner authorized exactly one database-free focused Hotfix16 source-contract run plus syntax, Blade, route, locale, diff, and conditional frontend-build checks. Database and browser execution remain unauthorized.

## 2026-09-06 — Hotfix17 POS compact refund and financial reversal boundary

- Hotfix16 durable open orders and `CompletePosOpenOrderAction` remain the sole POS sale-posting path. Hotfix17 changes proportions and interaction placement without introducing a second cart, checkout, payment, inventory, tax, rounding, receipt, or idempotency implementation.
- Refunds extend the existing `RetailReturnAction` lifecycle and tables. Creation reserves remaining refundable quantities; submission remains separate from approval; a non-Super-Admin creator cannot approve or reject their own request; only an approved return can complete.
- Completion serializes on the immutable source sale, locks source/return/payment rows, revalidates company/branch/outlet, quantities, return locations, totals, taxes and Hotfix14 rounding, and posts inventory plus append-only settlement portions in one transaction. A stable completion key and payload hash make retries safe; original sale, payment, and stock rows are never rewritten.
- Any refund allocation using cash requires the cashier's current active assignment to the exact original branch, selling outlet, and drawer. Card/electronic portions never expose cash received, denominations, or change, and safe references pass the existing PAN/CVV guard.
- The current retail sale architecture has no general-ledger journal posting service. Hotfix17 therefore does not invent or duplicate a ledger: it preserves the established operational accounting contract through immutable source-linked refund settlements, reversing inventory movements, shift expected-total deductions, and a financial reversal snapshot for gross revenue, discount, tax, cash rounding, settlement allocation, and returned inventory cost.
- Refund printing resolves only an active `sales_return` template/printer matching the return company, permitted branch/outlet, paper size, and locale/bilingual setting. Browser print remains explicit fallback; cross-scope silent printing is prohibited.

## 2026-09-07 — Hotfix18 corrective activation boundary

- Hotfix18 changes no POS or refund behavior: it retains exact Hotfix17 functionality and repairs only compiler-confirmed Blade/PHP structures in the POS page, cart, checkout panel, and purchasing-return print fallback.
- The owner-verified production baseline is exact Hotfix16 while additive migration 000111 is already Ran. Activation must accept 000111 as either Ran or Pending, run the normal idempotent migration command as `rajeh`, and require it to be Ran afterward; no new migration is permitted.
- Promotion and activation must prepare `bootstrap/cache` with `rajeh:rajeh` ownership and mode `0770` before every possible Artisan call. Exact Hotfix16 remains both the required active release and failure rollback target.


## 2026-09-07 — Hotfix19 Livewire root and extracted-cache boundary

- Hotfix19 retains all Hotfix17/18 POS and refund behavior. The closed refund wizard must still render one permanent Livewire root; its dialog and every conditional state remain inside that wrapper.
- Promotion must create the extracted target bootstrap cache with mode `0775`, recursively set `rajeh:rajeh` ownership, and verify writability as `rajeh`. Activation repeats that preparation before any Artisan command and verifies writability immediately before and after cache generation.
- Exact active Hotfix16 is the only accepted activation baseline and exact rollback target. Migration 000111 is already Ran and must remain Ran; Hotfix19 adds no migration and activation does not execute a migration command.

## 2026-09-18 — Hotfix43 financial snapshot and tax ownership — Approved

- Existing schema cannot preserve the PO `tax_inclusive` fact on `purchase_invoice_lines`; inferring it after settings or amounts change is ambiguous. Proposed migration 000117 adds only a non-null boolean snapshot, backfills linked rows from immutable PO lines, keeps confirmed zero-tax unlinked production rows false, changes no money, and fails closed on rollback if inclusive history exists.
- Existing `tax_settings` rows have no company owner, so a company-specific default or product override cannot be authorized reliably. Proposed migration 000118 adds only restricted company ownership, replaces global code uniqueness with company/code uniqueness, and adds the scoped lookup index. Legacy backfill proceeds only when company ownership is unambiguous; current production has one company and four rules.
- The owner approved migrations 000117 and 000118 exactly as proposed. Both use fail-closed preflight/down behavior, tolerate their own partially applied DDL, and may run only after actual schema/migration inspection. They are required to close original requirements 10, 11 and 65 plus OC-PO-SNAPSHOTS.
- Remain unresolved without invention: cross-branch receiving; independent customer-code sequence interpretation; removal of document timezone fields; and deferred comparison C whose two referenced items are unidentified.

## Historical Decision Records Restored — 2026-10-01

DEC-085, DEC-086, and DEC-087 below are restored verbatim from `.ai/DECISIONS.md` at commit `47da09c15b3d568bb2880a3e8c6d00900fe4c63d` (2026-08-20). Commit `c68f87c20d79085705d3310f1e2c233679dad8e3` (2026-08-22) removed these entries with the earlier decision register. Their restoration resolves surviving documentation references; it does not create new owner decisions or revive historical execution authorization.

The original dates, statuses, and wording below describe their August sessions. In particular, “active” and “current exception” are historical wording, not current testing, browser-control, agent-delegation, production, or release authorization. Current owner instructions, `AGENTS.md`, and `.ai/CURRENT_TASK.md` govern present work. DEC-086 superseded DEC-085's narrow queue sequencing within that historical cycle. DEC-018 and DEC-021 cited inside DEC-086 remain historical provenance references; this bounded restoration does not recreate the rest of the removed register or resolve those owner decisions.

The Translation Editor's surviving DEC-085 citation was incorrect: DEC-085 describes remediation, while DEC-087 records the runtime translation override boundary. Its screen-registry citation now points to the restored DEC-087.

## DEC-085 - Client feedback remediation cycle and task-gated verification

Date: 2026-08-19. Status: active owner-authorized remediation cycle.

The owner supplied `docs/Master Change Request — Client Feedback Remediation & Setup UX Overhaul.md` as the primary source for a task-by-task setup remediation cycle. Work is ordered through `docs/client-feedback-remediation-checklist.md`; only one main `CR` task may be active, Luna performs read-heavy discovery, Terra implements and tests only after Sol confirms the root cause, and Sol alone closes a task after independent review.

For this named cycle, the owner explicitly requires focused backend/feature/integration/concurrency tests and visible headed Chromium/Playwright verification for user-facing workflows. This is a current exception to the general deferred-testing directive. Tests and fixtures must use only the dedicated disposable XAMPP MariaDB schema `toyjoy_client_feedback_20260819`; SQLite and Production data are prohibited. The authorization includes deterministic non-owner fixtures needed to reproduce setup, scope, authorization, audit, and persistence behavior, but does not authorize Production access or mutation, fabricated owner business data, physical printer claims, external service actions, human UAT acceptance, release approval, commits, or pushes.

## DEC-086 - Owner-authorized expanded master-request remediation planning

**Date:** 2026-08-19. **Status:** Active owner authorization; implementation and verification incomplete.

The owner directed completion of every remediable note in `docs/Master Change Request — Client Feedback Remediation & Setup UX Overhaul.md`, including requirements 0–72, their acceptance/test obligations, and the document's P0/P1/P2 priority order. This supersedes DEC-085's narrow single-queue sequencing as the implementation-planning boundary. The simplified `CLIENT FIX QUEUE` remains the factual progress ledger and must not be rewritten to imply that unchecked work is complete.

The authorized planning waves are: Wave 0/P0 setup blockers and integrity foundations; Wave 1/P1 setup architecture and business configuration; Wave 2/P1 customer/supplier/category master data; and Wave 3/P2 plus cross-cutting localization, scope, audit, authorization, concurrency, regression, evidence, and final-deliverable closure. Independent work may be coordinated in parallel by multiple agents; shared-file edits require coordination. The expansion does not authorize unrelated product work, silent requirement changes, or claims of completion from static inspection.

The following remain unresolved owner/business decisions or external gates and must not be invented during remediation: Store versus Warehouse domain terminology/model boundary (DEC-021); offline POS enablement, limits, devices, security, and rollout (DEC-018); final tax/payment/proof semantics and legal treatment of zero-rated, exempt, and out-of-scope cases; warehouse physical-versus-virtual taxonomy and deletion/archive policy; document-sequence reset/scope/override/version rules where the master request conflicts with the local company-wide baseline; global-versus-branch scope and inheritance for taxes, payments, printers, sequences, and other masters; customer purpose/classification, duplicate/consent/retention, child-profile, and grouping policy values; supplier payment-term and order-recipient fallback rules; printer hardware/network, approved templates, and physical-output evidence; and real owner master data, human UAT, backup/restore destinations, Production access, and release approval.

Local implementation may use existing repository boundaries and clearly labelled disposable fixtures only where needed for a reproducible scenario. No Production mutation, fabricated owner data, physical-device claim, UAT acceptance, release approval, commit, or push is inferred from this decision.

## DEC-087 - Runtime translation override boundary

Date: 2026-08-20. Status: implemented for Local/Dev verification.

Administrators with `company_settings.edit` may override only keys collected from shipped `lang/ar.json`, `lang/en.json`, and their PHP groups. Overrides are database rows applied by the Laravel translation loader at request load time; they never modify language files. A matching base value deletes the override, placeholders must remain intact, and every actual change is audited.

## DEC-088 — Permanent scoped QA Gate and continuation authorization (2026-10-04)

**Status:** Approved — explicit owner request for permanent Fast/Release QA tooling and checkpoint-only continuation, commit/push and clean main integration. Applies to the named gate only: focused PHPUnit regressions, loopback disposable Admin/User/Cashier Playwright checks, Larastan/PHPStan, Semgrep, scoped Infection and preserved socket-only native MariaDB/reconciliation/restore. Preserve completed checks and do not repeat successful coverage after a fix. General testing/database policies and production deployment workflow remain unchanged outside this owner-directed QA workflow. No production business data may be used for destructive QA.

**Implementation choices:** Fast limits static analysis to five critical arithmetic files and twelve exact existing type-inference baseline findings; the earlier broad attempt's 1,404 diagnostics remain debt. Release mutation initial minimum 50%, improvement target 70% with warnings below target; observed corrected score 53.41%, 191 survivors. The temporary 70% exploratory target was not met; it is retained as an improvement target, not represented as passing. An apparent 100% Infection result was invalidated because JSON-only passing PHPUnit stdout was misclassified; canonical-footer adapter and replay of only survivors produce the recorded score. These controls do not establish full financial assurance, human UAT or unconditional client handover.


## DEC-089 — QA Hardening Phase 2 and confirmed inclusive-tax correction (2026-10-04)

**Authorization:** Explicit owner-directed Phase 2 scope: meaningful critical mutation/domain/matrix/concurrency checks in socket-only disposable MariaDB; target corrected MSI minimum 85%, preferred 90%+, individual equivalent/ignored reasons, no hiding survivors, focused static/security and actual GitHub Fast verification. No broad audit or repeated unrelated passing checks; production deployment workflow remains unchanged. Source baseline `ed0e98e5eb259d1e7289272e2edbf20cd22de0df`, dedicated `feature/qa-hardening-phase2`.

**Confirmed D3 dependency:** Existing POS inclusive-tax extraction truncated the exclusive portion to four places before subtracting it: sale 0.04 at 14% reports tax 0.01, while exact `0.04 × 14 / 114 = 0.004912...` rounds half-away at the money boundary to 0.00. The independently generated rational matrix and direct calculator reproduction confirm it. Owner condition authorizes a minimal runtime correction: extract inclusive tax directly as `base × rate / (100 + rate)` with guard precision, then apply existing money rounding. No new tax setting/rate, rounding convention, invoice payable, historical snapshot rewrite or deployment is implied. Purchase oracle retains its documented four-place snapshots and eight-place intermediate precision; it does not impose a different fractional cost rule.

**Second confirmed D3 dependency:** At valid weights 1,000,000.00 / 1,000,000.01 / 1,000,000.02 and discount 0.01, truncating all share remainders to six places incorrectly ties them and allocates the penny to the smallest line. The declared largest-remainder rule requires the third line. Allocation now uses exact integer-cent numerator/remainder arithmetic and stable decimal comparison; the document discount, tax policy and total remain unchanged. Focused large-weight and tie-order regressions verify it. No unrelated runtime refactor is included.

**Third confirmed D3 dependency:** Two real SaveProductAction transactions contending on the same existing supplier sequence deadlock during insert-or-ignore/shared-to-exclusive locking, with one caller receiving SQLSTATE 40001/1213. The whole product-card transaction now uses Laravel's bounded three-attempt deadlock retry. The isolated race verifies distinct codes/barcodes, exactly two sequence increments and one audit event per committed product; rolled-back attempts leave no extra product/audit effect. No schema, permission or identity format changes. These three runtime repairs remain undeployed pending an explicit release task; QA Phase 2 does not prepare or activate a production artifact.

**Mutation reporting:** Phase 2 preserves raw covered Infection MSI alongside an individually reviewed adjusted score. Exact file/patch/source fingerprints constrain every equivalent/unreachable exclusion; no whole mutator is disabled. Raw MSI below 85% remains a visible Release warning even when the reviewed score reaches 85/90%. Exception-text/coercion and unproven precision survivors remain counted. Three exact pre-existing SaveProductAction static diagnostics are baselined only after identical before/after comparison; the existing twelve arithmetic findings remain unchanged.


## DEC-090 — Phase 2 end-to-end closure and runtime release authorization (2026-10-04)

Explicit owner continuation now authorizes a clean history-preserving main merge when the only CI blocker is external API access, and deployment of the three confirmed application fixes through the established immutable release/backup/rollback workflow. This supersedes the earlier CI-required merge hold and undeployed-only scope for this named closure. No production fixtures, destructive QA, password/role/grant changes or unrelated services are authorized. New precision review keeps all twelve gaps and fifteen message/coercion survivors counted; no new exclusions or whole-scope mutation run. Fast remains lightweight: the new three-method precision suite is Release-only. Production smoke uses read-only routes and existing identities; writable financial/product/approval effects retain their actual isolated MariaDB evidence rather than inventing production test transactions.


## DEC-091 — Permanent optional Product Card cost (2026-10-05)

**Status: Approved — explicit owner business-rule correction.** Cost must never be required to save, finalize, activate or maintain a Product Card. Valid authorized input persists; omitted/blank input preserves any locked saved cost; new products may retain genuine null. No hidden cost input is required and no existing cost is silently cleared or replaced by zero. Existing cost-view/edit permission boundaries remain unchanged.

ProductPricingContract is the authoritative optional cost validator shared by form, final/draft save and Product Card workbook import. Purchasing/receiving explicit cost, original snapshots, valuation and opening-inventory cost safety remain their existing independent rules. Existing nullable master schema and historical snapshots are unchanged.

Owner authorizes only focused A–F tests, affected static analysis, browser save/reload, task branch commit/push, clean main integration, established production deployment and one compact smoke. The owner's explicit follow-up authorizes the socket-only disposable native MariaDB workflow for this named hotfix because XAMPP is absent. Historical/general testing and database directives remain outside this scope. No broad audit/Gate, migration or production business fixture is authorized or performed. Evidence: `.ai/evidence/product-cost-optional-permanent/RESULTS.md`.

## DEC-092 — Laravel Boost development tooling (2026-10-07)

**Status: Approved — explicit owner installation request.** Install `laravel/boost` as a development dependency and run its installer for the current Codex checkout. Composer selected Boost `v2.10.2` (`^2.10`), compatible with installed PHP 8.4.21 and Laravel 13.24.0; its MCP application-info call confirmed the local MySQL driver. The four new locked development packages are Boost, Laravel MCP 1.0.1, Laravel Roster 1.0.0 and Composer Semver 3.5.1. Every pre-existing package version was preserved. Primary installation reference: https://laravel.com/framework/docs/boost.

Codex guidelines, skills and the project-local Boost MCP connection were installed. Existing mandatory AGENTS content, CodeGraph connection and global Codex configuration were preserved. `config/boost.php` disables automatic test enforcement/listing and excludes the generic PHPUnit guideline so installation does not override the project's active testing directive. Existing authorization, database, release and deployment gates continue to control tool use. This approval authorizes local development tooling, not production deployment, business-data writes or general automated test execution.

Composer audit reports eight pre-existing advisories across `laravel/framework`, `league/commonmark` and `league/flysystem`; no unrelated package upgrade was included. Details are in ignored `storage/logs/boost-composer-audit.json`. MCP initialization/tool discovery and read-only application info passed; local login remained HTTP 200. Codex must reload its already-running tool catalog to expose the new connection.


## DEC-093 - Module 1 deep QA remediation authorization (2026-10-07)

**Status: Approved - explicit current owner request.** Investigate all pages and nine reported bugs using exactly ten concurrent Luna x-high subagents with isolated scopes/worktrees, test-first focused local fixes, lead integration, ten-agent red-team review, and final appropriate project-level verification including `php artisan test`. This named scope supersedes deferred testing for these findings only. Use existing tooling and dedicated disposable XAMPP MySQL schemas; never use application SQLite or the normal local business database for destructive tests. Do not modify production, credentials, real records, dependencies, grants in existing data, or deploy/push. Ambiguous policy remains unresolved unless approved sources establish it.

## DEC-094 - Module 2 rapid QA fixes and branch publication (2026-10-07)

**Status: Approved - explicit current owner request in the attached pasted text.** Seven confirmed bugs in the complete 21-page Module 2 report: BUG-105, BUG-106, BUG-231 through BUG-235; BUG-230 was withdrawn. Reuse exactly ten Luna x-high agents and their isolated worktrees, frozen source `07a514c94d106efe3516d81780ee9081ecfd86bc`, focused regression checks (stricter for imports, permissions and approvals), one branch per bug and one final integration branch. Named automation/local browser verification and successful bug/integration branch pushes are authorized. Reuse the recorded disposable XAMPP schemas and guarded test harness. No second ten-agent Module 2 review, repeated full suites per bug, dependency changes, real grants/data cleanup, production changes, force push or main merge. Preserve unresolved product decisions and the previous Module 1 closure obligations. Historical production records are not current verification.

## DEC-095 - Fast Module 1 QA batch (2026-10-07)

**Status: Approved - explicit latest owner request in attached pasted text.** Only BUG-01 through BUG-16 in `Module1-QA-Report-e79c59e0.pdf`; source `7c3cf6daaf1cff0c9d7a82b06c080a2a25113c1f`. Exactly ten Luna agents, HIGH by default and xhigh for sensitive domains, isolated `fix/bug-XX-*` branches, practical focused regressions, lightweight lead approval and immediate exact-branch pushes. One final integration branch `fix/module1-qa-fast-integration`, one full-suite run and one frontend build if needed. This named automation authorization supersedes deferred testing and the preceding completed three-test closure restriction for this batch only. The tool initially rejected the tenth launch/reuse with its thread limit; after two read-only lanes completed, the tenth launch succeeded. No second review team, repeated broad verification, dependency changes, real data/grants cleanup, production inspection/deployment, force push or main merge. Approved policy remains authoritative; unresolved admission/assignment rules and unavailable remote infrastructure are reported without invented defaults.

## DEC-096 - Ultra-fast Module 2 BUG-17 through BUG-29 (2026-10-07)

**Latest owner amendment:** one branch per QA module, one clear commit per bug; investigator/reviewer subagents only and no additional per-bug pushes. Current branch `fix/module2-qa`; consolidate verified history and preserve drafts before deleting obsolete local/remote branches and temporary worktrees. Owner explicitly authorizes this cleanup and merging away from main. Main/master remain read-only; production access/deployment is not authorized. Existing per-bug pushes occurred before this amendment and will be consolidated/deleted safely.

**Status: Approved - explicit owner request in attached pasted text.** Original clean source `386bf312b6b527463f88d236f20df5a941122acb`, six Luna agents only (pricing xhigh; five HIGH), isolated worktree lanes, one fix branch per bug and lead-approved exact-branch pushes; final `fix/module2-ultrafast-integration`. Named focused automation supersedes older full-suite/deferred-test directives for this batch only. Verify focused fixed-bug tests, changed PHP syntax, one frontend build if needed and one optimize-clear using isolated QA resources. Existing pricing policy and backend maker-checker protections remain authoritative. Supported XLSX import only; browser-only upload limitation is blocked. No full suite/full static scan, old-failure or static-debt cleanup, cache/AST investigation, extra helper result/publication scripts, extra agents/second review, main change, production access/deployment or invented pricing policy.

## DEC-097 - Module 5 POS fast QA authorization (2026-10-07)

**Status: Approved - explicit owner request in pasted attachment.** Only M5-01 through M5-11 from the POS QA PDF; one branch fix/module5-pos-qa-fast, five concurrent Luna investigators (three xhigh, two high), lead-only edits, focused automation, one frontend build and one isolated optimize-clear. Source baseline af6c2863271c82b3a99d733e0432c52f1897727f; dedicated disposable XAMPP database qa_rajh_m5_pos_20261007. Push only this branch. No main merge, browser authorization, deployment, new policy, broad audit/static/history cleanup, new QA infrastructure, or second review.

## DEC-098 - M5-10 audit export row cap (2026-10-07)

**Status: Requires Owner Decision.** audit.export.max_rows intentionally has no positive default and AUDIT_EXPORT_MAX_ROWS has no approved operational value. The owner must choose a positive row cap before export can proceed. Existing permission enforcement and fail-closed configuration behavior are preserved; focused missing-limit check passes. Affects M5-10 only and does not block the other POS repairs.

## DEC-101 — Supplier tax-registration format (Module 3 v2)

Status: Requires Owner Decision. M3-10 reports `ABC` accepted. Supplier UI/action/import currently treat `tax_number` as optional text (maximum 50 in the UI/schema); no approved numeric-only or Egyptian length/format rule was found in the routed requirements. Owner must define allowed characters, normalization and length/format before server validation is changed. This finding grants no permission to invent a tax rule or alter existing supplier data.

## DEC-102 — Audit display timezone precedence (Module 3 v2)

Status: Requires Owner Decision. M3-12 confirms audit views format timestamps without conversion. `docs/07-database-schema.md` specifies UTC storage and authorized branch/user display; company and branch timezone configuration exists, but user timezone storage and precedence for scoped versus global audit events are unspecified. Owner must define the display source/fallback precedence. Keep UTC storage and application timezone unchanged; do not hardcode Cairo or add an unsupported user setting.

## DEC-099 — Module 9 fast QA authorization (2026-10-07)

**Status: Approved — explicit owner request.** Named D3 scope M9-01/02/03/04/05/06/08 only, TSK-025/CSH-02/CSH-03. Four concurrent Luna investigators (state high, variance x-high, trace high, rounding high) investigate; lead applies related-group fixes to the single `fix/module9-cash-shifts-qa-fast` branch from current main `87db35e88d6d726b5e287e8e4dcec600ba71d941`. Focused native tests and isolated XAMPP MySQL permitted for this task; no SQLite, full audit, browser control, merge or deployment. One optimize:clear, changed syntax, one frontend build, task-branch-only commit/push. M9-07 is native-dialog tooling only. Existing nearest-5-EGP policy/control is reused unchanged.

## DEC-100 — Mandatory reason when accepting non-zero shift variance (2026-10-07)

**Status: Requires Owner Decision.** M9-05 display is fixed. docs/32 section 13 permits a review reason/comment; the existing approve/close action accepts an optional note, while recount requires a reason. No authoritative rule requires a note when accepting a non-zero variance. Only that policy requirement remains undecided; optional acceptance behavior and existing financial/approval guards are preserved. Affects TSK-025 shift variance approval, CSH-02/CSH-03; does not block the other Module 9 fixes.

## DEC-103 — Module 7 inspection rejection and attachments (2026-10-07)

**Status: Requires Owner Decision.** M7-06 asks whether a distinct Rejected inspection result and inspection photo/file attachments are required. PRD RET-03 and docs/26-discount-return-policy.md require inspection, approval and separate handling of non-saleable/damaged goods, but do not clearly require these additions; the precise condition list is configurable. Existing document approval rejection remains available. No new condition, attachment policy or workflow is invented. All other Module 7 Part 1 fixes and local P0 scenarios continue independently and are complete.

## DEC-104 — Purchase-invoice System Administrator self-approval (2026-10-12)

**Status: Approved — explicit owner hotfix request.** For purchase-invoice receiving and approval only, with an approval record whose `source_type` is `purchase_invoices` and `requested_action` is `approve`, the active canonical `system-administrator` role may approve an invoice it created, subject to the existing purchase-invoice permission, branch/store visibility, transaction, approval-record policy, and audit checks. The immutable Super Admin flag retains its existing bypass. Ordinary creators remain subject to independent approval. This narrow exception supersedes the 2026-09-27 maker/checker restriction only for purchase invoices; purchase orders, purchase returns, and other approval workflows remain unchanged. No username/email exception, migration, production change, or deployment is authorized by this decision.

## DEC-105 — Invoice-optional supplier returns and supplier payables (2026-10-17)

**Status: Approved — explicit owner request.** Supplier returns may reference an approved Rajeh purchase invoice or omit the invoice for stock purchased before Rajeh. Linked lines use the original invoice-line unit cost; invoice-free lines use and snapshot the product card's current cost at creation. Each return belongs to one explicitly identified valid supplier and one active source store. Approval reduces available stock and supplier payable by the return value, allowing a negative supplier balance. One append-only supplier payable ledger records approved invoice increases, approved return decreases, and opposite reversal entries with source identity and idempotent posting. Historical approved invoices are not silently backfilled, and no opening balance is invented. This narrowly supersedes the source-invoice requirement in the previous purchasing return contract. No general ledger, unrelated module, production migration, merge, or deployment is approved.

## 2026-10-20 — Locked live stocktake and draft safety rules

1. Open inventory count sessions do NOT freeze or block warehouse operations.
2. Sales, returns, receiving, transfers and valid inventory movements continue during stocktake.
3. Stocktake reconciliation accounts for inventory movements that occur during the counting period.
4. Per-product `counted_at` is used where available to accurately reconcile movements relative to the physical count. Canonical movement IDs provide the persisted ordering boundary for new lines.
5. Physical count entry does not mutate live stock. Stock changes occur only during approved settlement.
6. Inventory operational documents preserve user-entered draft/form state when validation or business errors occur.
7. Stocktake supports hardware barcode scanners and mobile-camera barcode scanning.
8. An active warehouse may have `branch_id = NULL`.

These are owner-locked rules for this feature batch.

## DEC-106 — Super Admin and General Manager operational authority (2026-10-21)

**Status: Approved — explicit owner locked rule, clarified 2026-10-21.** The account-level `is_super_admin` flag, the active `system-administrator` role, and the active `company-owner` role (General Manager) are all privileged operational authorities inside their own company. Each may approve/finalize its own documents and bypass normal operational permission, maker/checker, and approval-chain restrictions. Tenant isolation, valid state transitions, canonical posting, audit history, idempotency, and duplicate-posting protection remain enforced. Creator/requester and approver/actor identities and timestamps remain visible even when they are the same user. Normal users retain maker/checker restrictions. This supersedes the older blanket maker/checker restriction and subsumes DEC-104's narrower purchase-invoice exception for `system-administrator`; historical decisions remain as records of their earlier scope. This decision does not authorize a merge or deployment.

## DEC-107 — Header branch as default commercial context (2026-10-25)

**Status: Approved for this owner-assigned feature branch; undeployed.** The authorized header branch persists in the session and supplies defaults to supporting purchase, sales, POS shift-opening, and report views. It does not grant access, override document branch/store edits, or assign a branch to a nullable standalone warehouse. All Branches clears the default. Purchase-line displayed sale price follows the selected warehouse branch's mapped or sole selling outlet price list through the canonical resolver; List 0 applies only under that resolver's fallback rules. Draft-line labels use the existing barcode composer and current line quantity and contextual price without invoice posting or approval. POS payment mode changes preserve drafts in place, split remainder defaults to unpaid balance, customer lookup is debounced without page reload, and blind shift close displays only physical counted cash to the cashier. No schema or pricing precision change is authorized.

## DEC-108 — Multiple selling outlets without purchase label mapping (2026-10-25)

**Status: Requires Owner Decision.** A purchase warehouse belongs to a branch, but its invoice form has no selling outlet field. When that branch has multiple authorized selling outlets with distinct price-list assignments and no active primary mapping, the destination selling price cannot be chosen without inventing context. The draft-line label shortcut therefore shows a clear validation error; purchase draft saving and branchless warehouses remain available. The owner must choose whether the purchase document gains an explicit selling outlet or a canonical branch default assignment before this case can print labels. This affects the User B purchase-line price/label acceptance gate and does not authorize a change to User A inventory work.
