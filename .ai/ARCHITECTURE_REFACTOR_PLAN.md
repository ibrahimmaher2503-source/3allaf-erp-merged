# Foundation Architecture Refactor Plan

**Status:** Foundation slices completed on 2026-08-03.

## Current Catalog Extension — TSK-011 — 2026-08-04

TSK-011 extends the existing Catalog identity slice with additive product-card columns, an ordered `product_images` link to the existing private `attachments` foundation, full-page Livewire product create/edit/detail screens, bounded attribute/type filters, source-authorized media delivery, lock-version stale-update protection, and shared audit events. It does not add a generic media/attribute/variant engine, supplier master/history, composition workflow, pricing, inventory, labels, imports, purchasing, POS, or a new permission grant. Composite component behavior is deferred because the approved Phase 1 contract does not define enough composition policy to implement safely.

The local SQLite implementation and owner-authorized browser evidence remain local-development evidence only. TSK-010 is complete for approved local scope; TSK-009 remains at its actual recorded status; no Phase 1/Phase 2 gate, UAT, or production readiness is claimed.

## Current Catalog Extension — 2026-08-04

DEC-043 permits the bounded TSK-010 Catalog module slice after the available TSK-009 foundations. It uses the existing Laravel modular-monolith, Livewire full-page components, Flux UI, Gate/policy boundary, shared `RecordAuditEvent`, SQLite local migrations, and no generic repository/service abstraction. The slice stops at catalog identity, category/brand masters, and barcode association/allocation; TSK-011 media/type/composition/service behavior and TSK-013 supplier master/history remain deferred.

TSK-010 local closure verification completed on 2026-08-04: DEC-038-approved catalog `View (A)` grants were seeded for the verified local roles, catalog `P`/`R` capabilities remained ungranted, supplier duplicate/replay protection and category self-parent/descendant-cycle rejection passed in stable browser control, and database/static integrity checks passed. This closes only the approved local identity slice; it does not advance TSK-009, start TSK-011/TSK-013, or close any production/milestone gate.

## Shared Platform Guidance Boundary — TSK-004B

The existing modular monolith and Flux/Livewire shell are retained. TSK-004B adds a Platform Support registry, safe DTO, per-user presentation preference model, and shared Blade/Alpine controls without a new package, external AI provider, business module, or authorization matrix change. The bounded architecture is recorded in `docs/40-contextual-page-guide-specification.md`.

Implemented Foundation scope is limited to Platform and starter Auth:

- Platform Actions: `GetPlatformStatus`, settings, branch/store mapping, cash drawer, and user authorization actions were initially under `app/Actions/Platform`.
- Platform Models: company/settings, branch/store/drawer, role/permission/scope, numbering, printer, and settings audit models were initially under `app/Models`.
- Current surfaces initially used `routes/web.php`, `routes/settings.php`, full-page Livewire Blade files under `resources/views/pages/admin` and `resources/views/pages/system`, shared layouts/components, and starter Fortify actions.
- Authorization: canonical permission Gates live in `AppServiceProvider`; actions and Livewire methods authorize server-side.
- Audit: the historical `SettingsAuditLog` remains preserved for local traceability. TSK-009 now introduces the shared append-only `audit_logs` capability and migrates current Platform writes to it; approval, attachment, and immutable-document mechanics remain task slices in progress.
- Document numbering: `DocumentSequence` is a current settings master only; allocation is not implemented and must remain TSK-009/module scope.

Before the current TSK-010 slice, no Catalog, Purchasing, Inventory, Retail workflow, Cash Shift, Customer, Party, Asset, Reporting, or Offline business module was implemented. TSK-010 now adds only the bounded Catalog identity slice described above; no later module directories or speculative abstractions were introduced.

## Historical Blocker - Resolved

The initially referenced `08-application-architecture-minimal.md` was absent from the repository. Before this plan was executed, the owner confirmed that the supplied minimal architecture replaces and updates `docs/08-architecture.md`. The resolved source is now reflected in `docs/08-architecture.md` and DEC-041; it no longer blocks Foundation work.

## Execution Record - 2026-08-03

1. **Route boundary completed:** existing Platform routes now live in routes/platform.php; routes/web.php is the small loader and route paths, names, middleware, and permission strings are unchanged.
2. **Platform PHP boundary completed:** existing Platform actions/models now live in app/Modules/Platform/Actions and app/Modules/Platform/Models; callers, seeders, and User imports were updated. User and Fortify remain in their existing boundaries.
3. **Platform UI boundary completed:** existing Platform admin/system Blade Livewire pages now live in resources/views/platform; the registered platform Livewire namespace preserves their route aliases and layouts.
4. **Deferred capability unchanged:** document-number allocation remains TSK-009/use-case work. No repository, service, enum, or placeholder module was added.

Technical checks passed after each slice: PHP lint, route discovery, and Blade view caching. Manual browser verification was not run during this refactor and remains governed by the active task directive.

## Historical Proposed Incremental Stages

1. **Route boundary only:** extract existing Platform routes from `routes/web.php` into `routes/platform.php`, load it from `web.php`, preserve paths, names, middleware, and views exactly. Run syntax, route discovery, view cache, and manual browser smoke checks.
2. **Platform namespace boundary:** move only existing Platform models/actions to `app/Modules/Platform/Models` and `app/Modules/Platform/Actions`; update namespaces/imports in one coherent slice. Keep `User` and Fortify starter files in place. Run the same checks.
3. **Platform UI boundary:** move only existing Platform admin/system Blade Livewire files to `resources/views/platform`; retain component aliases, routes, layouts, and page behavior. Run the same checks.
4. **Shared capability extraction when used:** centralize audit writing and document-number allocation only when TSK-009 implements their approved behavior. Do not add unused facades, repositories, services, enums, or placeholder modules now.

Each stage is independently reversible. Do not start the next stage until the previous stage preserves route names, authorization, audit writes, and visible behavior.

## UI Foundation Refinement Slice - Planned 2026-08-03

**Scope:** Current Platform application shell, shared Blade composition patterns, UI Showcase, and the Authorization Baseline presentation only.

1. Add semantic UI tokens to the existing Tailwind entry point and refine the existing Flux application shell, sidebar, and mobile header. No package, route, middleware, or authorization change is permitted.
2. Add only the shared composition components that are immediately used by the Showcase and Authorization Baseline: stat card, section card, data panel, filter bar, and form section. They compose Flux primitives rather than duplicate Flux controls.
3. Rework `platform::system.ui-showcase` as the visual contract for those shared patterns, then apply the same composition to `platform::admin.authorization-baseline`.
4. Move assignment-option reads out of the Authorization Baseline Blade template and load them only while its existing modal is open. Preserve every Gate, validation rule, action call, route, and persisted data behavior.

**Risks and verification:** This is a view/CSS and Livewire render-boundary refinement. The principal risks are Blade compilation, Flux attribute compatibility, responsive overflow, and a modal that no longer receives its options. Verify with the current manual-browser-only policy: authenticated rendering, the authorization modal, validation, denied direct access, Arabic RTL and English LTR at desktop and mobile. No automated test code or automated browser execution is allowed for this slice.

## UI Foundation Refinement Slice - Implementation Record 2026-08-03

- Added semantic tokens to the existing Tailwind entry point and refined the existing Flux sidebar into compact Dashboard and Platform groups. No package, route, middleware, Gate, Action, Model, migration, or permission changed.
- Added immediate-use Blade compositions for stat cards, section cards, data panels, filter bars, and form sections. They compose existing Flux primitives and do not create a second component framework.
- Reworked the Platform UI Showcase into the current visual contract and applied its data-panel, stat-card, filter-bar, and compact spacing patterns to Authorization Baseline.
- Authorization Baseline now eager-loads the displayed role names and supplies role, branch, and store choices only while its existing modal is open. Gate checks, validation, route identity, action invocation, and persisted authorization behavior are unchanged.
- Technical checks passed: PHP lint for the modified Blade files, `php artisan route:list --path=admin`, `npm run build`, and `git diff --check`. `php artisan view:cache` was attempted but exceeded the execution limit of this environment, so it is not recorded as passed.
- Manual browser verification remains required for this slice. No automated test or browser automation command was created or run.

## Production Performance Baseline - Inventory 2026-08-03

- The current Vite build has one very small common JavaScript entry and a separate passkey entry. No chart, table, jQuery, editor, or future-module JavaScript library is loaded globally. The latest production build reports 0.16 kB for `app.js`, 12.08 kB for the separate passkey entry, and 34.12 kB gzip for `app.css`.
- Current list queries are paginated, and Authorization Baseline eager-loads displayed roles. The Platform inventory also found query construction and lookup-table reads embedded in the Branch, Store, and Cash Drawer Blade files. Cash Drawers is the clearest next narrow slice: it currently reads filter options and modal lookups in Blade on every Livewire render.
- Existing Platform migrations already contain code uniqueness and query-oriented branch/store/drawer indexes. No new index is proposed until a real production query profile or a new documented query pattern justifies it.
- Production OPcache, Composer install flags, Laravel cache generation, HTTP compression, immutable asset headers, PHP runtime settings, queue worker, scheduler, Redis, storage, monitoring, and CDN configuration are deployment concerns. They are now documented in `docs/08-architecture.md` but remain unconfigured and unverified until the production environment is approved.
- **Next safe implementation slice:** after manual verification of the completed UI-foundation slice, move Cash Drawer list and lookup queries from Blade into its Livewire render method, keep all existing filters, scope, validation, and actions unchanged, and select only rendered columns. Do not add caching, Redis, queues, or speculative indexes.

## TSK-009 Audit Foundation Slice - Implementation Record 2026-08-03

- Added a central `audit_logs` table, append-only `AuditLog` model, `RecordAuditEvent` action, field redactor, and `AuditLogPolicy` under the existing Platform module.
- Backfilled the two existing local `settings_audit_logs` rows. `SettingsAuditLog` remains a historical compatibility record; new Platform settings, branch, store, cash-drawer, mapping, and authorization mutations write through the shared audit action inside their existing transactions.
- Added the server-gated, scoped, paginated `/admin/audit` Livewire screen and linked it only for `audit_logs.view` users. It includes bounded filters and a redacted before/after detail view; no export control was added because no current role has an approved audit-export grant.
- Approval inbox/records, attachment storage/delivery, immutable transactional states, correction records, and a number allocator are not fabricated here because no current source document or attachment-owning workflow exists. Manual browser verification is required before a later TSK-009 slice.

## TSK-009 Audit Browser-Control Review - 2026-08-03

- Browser-control inspection found no behavior change in the audit query or authorization boundaries, but did find two presentational defects: mobile table columns were inaccessible and the authorized zero-result state was invisible on desktop. The audit view now uses mobile event cards and an explicit empty state; the subsequent browser-control captures showed no page-level horizontal overflow at `390x844` and the Reviewer empty state rendered correctly.
- The remaining audit verification is not architectural refactor work: populated branch/store isolation, cross-scope detail denial, safe nested-secret redaction evidence, multi-page pagination, idempotent backfill rerun, and the remaining Platform mutation/failure cases remain pending. A UI attempt to assign a branch scope to the Reviewer was rejected by the existing final-System-Administrator validation and made no data change.
- Interactive manual Chrome could not be launched because the environment policy rejected `Start-Process` before process creation. The owner-authorized local browser-control run is recorded separately and does not claim manual verification or allow the next audit-dependent slice to be considered verified.
## TSK-009 Audit Verification Follow-up - 2026-08-03

- Audit redaction now covers API keys, authorization values, and cookies in addition to password/secret/token families.
- Legacy settings-audit backfill has a stable `legacy_source_key`, an idempotent Platform Action, and a maintenance command. It is not a dual-write path.
- Authorization Baseline modal state is independent from the edited user identifier, preventing client modal booleans from changing a save target.
- The Audit screen uses scope-first listing with policy-authorized detail access, and has explicit mobile pagination controls.

## Audit Foundation Browser Verification - 2026-08-03

The current Platform audit mutation boundary is verified: each representative successful mutation commits its business record and one audit row in the same action transaction; validation, authorization denial, and the exercised cross-branch drawer failure left no successful audit row. Store mapping explicitly returns the existing active mapping on an identical resubmission without a duplicate audit record.

## TSK-009 Approval Foundation - 2026-08-03

- This is a used Platform capability, not a speculative future module: source-owning modules will call a small request contract and named Actions rather than embed approval state machinery in models or controllers.
- The record is mutable only through a named pending-to-terminal transition; the append-only history is preserved by the related shared audit event. It does not introduce a generic workflow engine, repository layer, or source-document abstraction.
- No Platform master was made approval-capable because no approved requirement calls for it. Approval UI and source binding remain deferred until a real source document exists.

## TSK-009 Protected Attachment Foundation - 2026-08-03

- Platform owns only the protected attachment mechanics: validation, private storage, source-reference metadata, delivery guard, lifecycle actions, and audit context. Source modules own their policies and callbacks.
- `StoreAttachment` never replaces an existing record. A source module creates a new record for a replacement and preserves the prior record; its relation/version semantics remain source-task work because no current source exists.
- No public disk, generic media library, repository, upload screen, or public delivery route was introduced. The configured local disk is private; production disk/provider, scanning, quotas, and retention remain deployment/owner decisions.

## TSK-009 Immutability and Correction Foundation - 2026-08-03

- Added a narrow `ImmutableSourceContract`, `CorrectionReferenceData`, `CorrectionType`, focused guards, and `ExecuteCorrection` transaction boundary under Platform. These are integration conventions for real source modules, not a generic workflow engine.
- Correction callbacks own source authorization, allowed types, persistence, compensating effects, duplicate lookup, and approval assertions. The boundary preserves the original source, audits the correction context atomically, and rolls back both effects and audit on failure.
- `CorrectionNumberAllocator` documents the future `document_sequences` boundary without implementing allocation. No current Platform entity is a correction source; no migration, UI, route, or business module was created.
- Canonical routing references are `docs/37-ui-screen-specifications.md` and `docs/38-print-export-specification.md`; the previously named aliases do not exist and are not used.

## TSK-009 Final Closure Review - 2026-08-04

- TSK-009 is **Completed for approved local infrastructure scope**. The four Platform foundations share append-only audit writing, source type/ID references, branch/store fields, request IDs, safe redaction, transaction boundaries, and source version/hash conventions.
- Authenticated expiry paths now require explicit authorization callbacks; scheduler/system expiry remains available. Approval, attachment, and correction events pass their persisted request IDs into the shared audit writer.
- Deferred integration register: approval binds to the first legitimate approval-requiring document; attachments bind to product images, payment evidence, imports, returns, party evidence, and asset-condition media; immutability/correction binds to purchase, inventory, POS, returns, shifts, party, gift-card, quotation, rental, and other approved documents; number allocation binds per numbered transaction.
- No generic workflow engine, media library, correction table, future module, route, screen, or production number allocator was added. No Phase 1 gate, UAT acceptance, or production readiness is claimed.

## Phase 1 Closure Audit Impact — 2026-08-03

- The closure audit preserved the existing Laravel modular-monolith, `app/Modules/Platform`, `routes/platform.php`, Blade/Livewire, Flux, Tailwind, and Vite boundaries. No TSK-009 architecture slice was started.
- The only implementation changes were narrow closure fixes: server-visible drawer validation and same-branch selling-store mapping filtering/guarding. No repository/service abstraction, generic workflow, package, ordinary-screen API, or separate frontend was introduced.
- TSK-001 and TSK-005 remain open for named local gaps; TSK-002, TSK-003, TSK-004, TSK-006, and TSK-007 are closed for approved local scope; TSK-008 remains Completed; TSK-009 remains In Progress.
- Static checks and browser evidence are recorded in `.ai/TEST_RESULTS.md`. Production runtime, backup/restore, devices, master data, policy values, UAT, Phase 1 gates, and production readiness remain outside this refactor audit.

## TSK-011 Final Closure Review — 2026-08-04

- TSK-011 is Completed for approved local scope. The additive product-card schema, protected product-image linkage, source-authorized delivery, full-page Livewire card/detail UI, stale/version guard, immutable identity guard, and audit boundaries remain narrow extensions of the existing Catalog and Platform foundations.
- Composite component lines, assembly, bundle pricing, imports, supplier history, pricing, inventory, labels, and POS remain outside this architecture slice. The local PHP upload_max_filesize=2M boundary is documented; localized client/server-boundary messages handle oversized upload feedback without changing limits or adding a second upload engine.
- No Phase 1/Phase 2 gate, UAT, production readiness, commit, or push is claimed.
