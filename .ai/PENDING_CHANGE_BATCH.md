# Pending Change Batch

**Status:** HOTFIX17 CANDIDATE PREPARATION — NOT DEPLOYED
**Scope:** v0.1.22-hotfix17 POS compact redesign and refund workflow
**Opened after:** operator-verified `v0.1.22-hotfix16`
**Source baseline:** exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`
**Production baseline:** owner supplied: version `0.1.22-hotfix16`, commit `31883f5c823458d30738708ec96e1d02337f4c9b`, migration 000110 Ran, maintenance OFF, smoke PASS.
**Baseline disposition:** Hotfix16 is already deployed and is outside this batch. It was not rechecked.

Do not clear or archive this ledger until an authorized operator confirms successful Hotfix17 production activation and smoke verification.

## Pending Changes

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V1/D3 | Compact desktop POS, cart-contained durable order tabs, icon-only automatic customer/product search, compact payment cards and wide responsive checkout | Hotfix17 release commit | POS Livewire/components/views; existing Hotfix16 routes and managers preserved | VERIFIED — NOT DEPLOYED |
| D3/I4 | Full/partial source-linked refund wizard with independent approval, exact remaining quantities, authorized return locations, locked/idempotent completion and immutable reversal snapshots | Hotfix17 release commit | Migration 000111; return action/calculator/models; POS refund component/view/routes | VERIFIED — NOT DEPLOYED |
| D3/I4 | Original/split/cash/store-value refund settlement, exact-outlet cash shift/drawer enforcement, reversing inventory/shift evidence, audit and safe references | Hotfix17 release commit | Return settlements; inventory movement reuse; shift reconciliation; audit integration | VERIFIED — NOT DEPLOYED |
| V1/D2 | Company/branch/outlet/document-type-safe refund receipt routing with thermal/A4 browser-print fallback and Arabic/English parity | Hotfix17 release commit | Return receipt resolver/view/routes; locale JSON | VERIFIED — NOT DEPLOYED |

Production deployment, backup execution, migration execution, active-symlink modification, service operation, browser testing, tag, push, and remote contact are outside candidate preparation and remain unexecuted.
