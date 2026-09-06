# Pending Change Batch

**Status:** HOTFIX16 CANDIDATE PREPARATION — NOT DEPLOYED
**Scope:** v0.1.22-hotfix16 POS checkout and multi-customer workflow
**Opened after:** operator-verified `v0.1.22-hotfix15`
**Source baseline:** exact Hotfix15 commit `68699be35880dc154519c4e293c1a32bb69ef967`
**Production baseline:** owner supplied: version `0.1.22-hotfix15`, commit `68699be35880dc154519c4e293c1a32bb69ef967`, migration 000109 Ran, maintenance OFF, smoke PASS.
**Baseline disposition:** Hotfix15 is already deployed and is outside this batch. It was not rechecked.

Do not clear or archive this ledger until an authorized operator confirms successful Hotfix16 production activation and smoke verification.

## Pending Changes

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| D3/I4 | Durable exact-scope POS open orders with independent customers, cart approvals, notes, payment drafts, totals, cancellation, and idempotent new-order creation | Hotfix16 candidate | Migration 000110; open-order models/manager; POS cart, route, and UI integration | VERIFIED — NOT DEPLOYED |
| D3/I4 | Cash, electronic, multi-card, and mixed payment modal using existing payment configuration, Hotfix14 cash rounding, atomic sale services, and duplicate prevention | Hotfix16 candidate | Checkout Livewire/view, safe-reference guard, atomic open-order completion wrapper | VERIFIED — NOT DEPLOYED |
| D3/I4 | Stay-on-POS success flow and scoped configured thermal/A4 receipt preview with safe browser-print fallback | Hotfix16 candidate | POS success UI, receipt profile resolver, receipt templates/routes | VERIFIED — NOT DEPLOYED |
| V1 | Arabic-first responsive POS/customer UX with bounded server-side search and full English parity | Hotfix16 candidate | POS Blade/Livewire, customer routes/search indexes, Arabic/English locales | VERIFIED — NOT DEPLOYED |

Production deployment, backup, migration execution, active-symlink modification, service operation, tag, push, and remote contact are not part of candidate preparation and remain unexecuted.
