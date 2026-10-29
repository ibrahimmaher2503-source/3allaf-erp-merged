# Rajeh v2.0 — Core Operations Baseline

**Formal version:** Rajeh v2.0
**Semantic version:** v2.0.0
**Git tag:** `v2.0.0-core-operations`
**Application baseline SHA:** `55d4d5525ced50812f65941bb048dc81c6c7f641`
**Core Operations phase:** COMPLETE
**POS phase:** COMPLETE
**Next phase:** Major module expansion; no new major module has started at this freeze.

The tag names the exact application code running in the protected Production release. Later main commits may update documentation and tools without changing that application baseline. The tag is immutable: never move or recreate it for future work. See [metadata](CORE_BASELINE_METADATA.md) for the recovery assets and [recovery](CORE_BASELINE_RECOVERY.md) for safe recovery choices.

## Protected Core areas

The following established behavior is Core-frozen:

- POS, POS sale lifecycle, Smart Split Payment, payment posting and calculations, payment/refund behavior, receipt behavior, and receipt barcode/reference architecture.
- Returns, linked refunds, partial returns and limits, and the permission-controlled Unverified Return fallback.
- Inventory ledger, stock balances, inventory movements, adjustments, stocktake lifecycle, and reconciliation.
- Purchasing and purchase receipt posting.
- Stock transfers, final transfer review, reviewer editing, atomic approval plus automatic dispatch, destination receiving, and partial receiving.
- Branch, store, and warehouse operational context.
- Shift lifecycle, Cash Movement, Blind Close, cash drawer accounting, and expected-cash calculation.
- Permissions, server authorization, Core audit trail, numeric presentation rules, RTL/LTR, and established Core UX behavior.

## CORE FREEZE RULE

Future modules must **extend Rajeh**. They must not silently modify established v2.0 Core behavior. If a new feature needs such a change, stop before implementation and report **CORE CHANGE REQUIRED**. Owner authorization must identify:

1. The exact existing behavior allowed to change.
2. The required new behavior.
3. The affected Core modules.
4. The regression scope.
5. The database and migration impact.
6. The rollback impact.

Without that authorization, do not silently refactor Core workflows; replace Core domain services; alter transaction posting, inventory accounting, transfer or return/refund behavior, shift/cash calculations, existing statuses, permissions, audit behavior, or established UI flow; or add destructive Core migrations.

## Module integration and merge gate

New major modules, including Accounting, HR, Payroll, CRM extensions, and Advanced Reporting, should have their own bounded architecture. Prefer **new module → integration boundary → existing canonical Core services/models/interfaces**. Avoid duplicate financial, inventory, or business rules. The presence of an integration boundary does not itself authorize changing Core behavior.

No major module may merge into the Production path until its own feature tests pass, `scripts/test-core-baseline.sh` passes, a Core Freeze review finds no unauthorized Core change, migrations are reviewed, and every intentional Core Change has explicit owner approval. The gate uses real existing tests on a disposable socket-only MariaDB database and fails non-zero on regression. The selected coverage and known omissions are recorded in the regression section below.

## Database and migration rule after v2.0

Module migrations should be additive by default: new tables, compatible nullable columns, indexes, and reviewed foreign keys. Destructive drops or column changes, rewriting existing Core business data or financial/inventory history, and migrations that change posting semantics require explicit owner approval. A Production database backup remains mandatory before Production schema or data mutation.

Do not run `migrate:fresh`, `migrate:refresh`, `db:wipe`, generic reset commands, or `ProductionSeeder` on Production without explicit owner authorization for a full PRE-LIVE reset. The v2 baseline snapshots are protected recovery assets, not routine test fixtures.

## Core regression gate

Run `scripts/test-core-baseline.sh` from a clean isolated worktree with branch-local Composer dependencies and no `.env` or cached Production configuration. It uses the existing guarded QA runner, creates a socket-only disposable MariaDB database, runs a selected Core suite, reports a failed suite, returns non-zero on failure, and removes the disposable runtime. It does not mutate Production.

Selected existing coverage: POS sale and payment posting; Smart Split and payment calculations; inventory movement and balance reconciliation; purchase receipt posting; automatic multi-destination transfer dispatch, partial receiving, duplicate/rollback protection; stocktake settlement and scope; linked/unverified returns, partial limits, refund idempotency and denial; cash movement, expected cash, electronic exclusion, and blind-close secrecy. It does not claim physical printer/scanner UAT, a full browser matrix, or the Windows-only legacy Module 7 harness. Those remain separate checks where relevant.

## Protected v2.0 recovery assets

Normal automated or manual cleanup must preserve the annotated Git tag, the tagged application SHA, the immutable release and its `DO_NOT_DELETE_RAJEH_V2_CORE_BASELINE` marker, the database backup and SHA-256, the storage archive and SHA-256, the backup-directory protection marker, this freeze policy, the non-secret metadata, and the recovery procedure. Exact paths and hashes are in [metadata](CORE_BASELINE_METADATA.md). Do not delete these merely because newer releases exist.

## Version presentation boundary

Rajeh v2.0.0 is the formal version from this freeze. At freeze time, the protected immutable baseline release displayed `v1.22` through `App\Support\ApplicationVersion::RELEASE`. A separately authorized post-baseline metadata release (`262b298d122bbc0e13c44112fec6d9530a315369`) changed that one canonical value to `2.0.0`, displaying `v2.0.0`. The baseline tag and recovery release remain unchanged.

## Post-baseline authorized Core polish release — 2026-10-29

The owner explicitly authorized the nine-area Core change batch and its deployment from source `7546dbc`, merged without squash as application SHA `6125482ab2a633a892f9ae854f5fb5dec10ee906`. The new immutable release is `/home/rajeh/releases/toyjoy/releases/toyjoy-v2-post-core-polish-6125482`. It changes International Barcode to arbitrary numeric strings with leading zeros, label pricing to selected selling-outlet context, transfer quantity to destination allocation sums with source-stock limit, and keeps one editable final approval that automatically dispatches. It adds a scoped destination receiving inbox, human store names and dedicated fixed-type Inventory Addition/Deduction pages, independent locked `IAD`/`IDD` numbers, direct Approve/posting with corrective reversal, and moves POS payment selection to Complete Sale while retaining the payment engine and larger cart. No migration was added or executed. Merged-main focused and Core regression gates passed; candidate and real-host checks passed. Physical scanner and real thermal printer QA are explicitly deferred and remain **POST-DEPLOYMENT HARDWARE QA PENDING**. This is a post-baseline release; tag `v2.0.0-core-operations`, protected SHA `55d4d5525ced50812f65941bb048dc81c6c7f641`, and protected release/recovery assets remain unchanged.

## Authorized post-baseline POS quantity hotfix — 2026-10-29

Owner-authorized application `9d1363cadecc99c475c0e795cbd1702d4ef05712` synchronizes visible and canonical POS cart quantity and enforces the established selling-store stock cap across product-card, quantity controls, and checkout payload. It does not move the permanent `v2.0.0-core-operations` tag or alter the protected baseline release. No migration was required.
