# Current Task — Post-Hotfix51 Operational Documentation

**Date:** 2026-10-01

**Change class:** V0 — documentation only

**GitHub/main source baseline:** `4182c08e108da7cb3ed1a119470edb65a3681392` (`4182c08`), repository `IS-Intelligent-Solutions/rajeh-erp`.

**Main worktree:** `/home/rajeh/worktrees/rajeh-erp-main`

**Documentation branch:** `docs/post-hotfix51-operational-baseline`

**Documentation worktree:** `/home/rajeh/worktrees/rajeh-erp-post-hotfix51-operational-baseline`

**Status:** Operational documentation updated for branch review. Commit and push are the authorized handoff steps; merge and deployment are not authorized by this task.

**Recorded production baseline:** Current application release Hotfix51, application commit `b709193815c548bb0100a0b641171c5af7aaf314` (tree `9be1c3dba9f8ee2be992496487d5d300916e252a`), release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix51-b709193815c5-r6`. This is the preserved deployment record, not a new production check.

Future development starts from current protected `main` using a new dedicated branch/worktree, never directly on `main`. Follow `AGENTS.md` for branch, remote, account, and CodeGraph guidance. Application code, databases, services, production, deployment workflow, and historical branches remain outside this documentation task. Broader client-feedback remediation scope and Phase Gates are unchanged.

## Historical Hotfix51 Closure — 2026-09-30

**Historical source baseline:** exact deployed Hotfix50 application commit `0ff36dd24542d8378f6e9c24784e992b1d56ad74` (tree `90d224119ad75269627fce4b2c4cb96a8d02dfb2`) at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix50-0ff36dd24542`.

**Historical branch/worktree:** `hotfix/v0.1.22-hotfix51-product-supplier-finalization` at `/home/rajeh/worktrees/rajeh-erp-hotfix50`. These identify the completed Hotfix51 session only; they are not the current development starting point.

**Historical status:** Complete and deployed. The following scope and verification results are preserved from the 2026-09-30 record; they were not rerun for this documentation update.

Hotfix51 uses one product-code resolver for preview, Product Card completion, and purchasing-invoice inline creation. Canonical supplier `SUP-0002` resolves to `0002-000001`; numeric supplier `0123456` resolves to `0123-000001`. Leading zeros, prefix-shared locked sequencing, collision checks, existing product codes, and separate international barcodes are preserved.

Product Card `Save` is an explicit finalization path: it either commits a complete product with its local identity barcode or returns localized field/action errors, and never silently falls back to a draft. `Save draft` remains explicit. Completing an existing draft updates that same product and creates the required local identity barcode without duplication.

Authorized supplier deletion is available with confirmation only for unused suppliers. Linked suppliers are retained with a localized dependency explanation and may use the existing authorized deactivation path. Ordinary product-form explanations were moved into contextual help while actionable validation remains inline.

The focused rollback verifier passed 50 assertions in restored MariaDB `toyjoy_hf48_restore_368d44f2acea961a9a`. It created only disposable isolated records and rolled them back; no real supplier was deleted or deactivated. Deployment completed through the established writer fence, fresh encrypted backup, isolated restore/reconciliation, cache preparation, atomic activation, runtime restoration, and recovery controls. Journal: `/var/lib/toyjoy/hotfix51-deployment/b709193815c5-20260930.4qsXOu`; recovery archive SHA-256: `536f3a2998cb0abc376d17e24dd5991ec373ec26050ba343abde1f036a858213`.

Read-only live Arabic and English checks passed the empty-catalog Product Card create screen and Suppliers page, including direction, contextual help, delete/deactivate controls, and version. HTTPS `/up` and `/login` return 200, PHP-FPM is active, maintenance is off, migrations are unchanged, and no in-scope blocker remains. See `.ai/HOTFIX51_CHECKPOINT.md` for the exact operational record.
