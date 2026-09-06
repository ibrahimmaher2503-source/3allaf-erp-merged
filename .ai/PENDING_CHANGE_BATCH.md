# Pending Change Batch

**Status:** HOTFIX18 CORRECTIVE CANDIDATE — NOT DEPLOYED
**Scope:** v0.1.22-hotfix18 POS Blade and runtime-preparation repair, retaining all Hotfix17 work
**Opened after:** operator-verified rollback to exact `v0.1.22-hotfix16`
**Source baseline:** exact Hotfix17 commit `6f47db7a62b38e8bac2aa9a4983a63611a8c17e4`
**Production baseline:** owner-verified: exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`, migration 000111 already Ran, maintenance OFF.
**Baseline disposition:** Hotfix16 remains active and is the exact Hotfix18 activation prerequisite and rollback target. It was not rechecked.

Do not clear or archive this ledger until an authorized operator confirms successful Hotfix18 production activation and smoke verification.

## Pending Changes

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V1/D3 | Full Hotfix17 compact POS, checkout, customer/product/scanner, order-tab and payment-card redesign | Retained in Hotfix18 | Hotfix17 POS Livewire/components/views | VERIFIED — NOT DEPLOYED |
| D3/I4 | Full Hotfix17 source-linked refund, inventory/payment/accounting/authorization/audit/idempotency and receipt workflow | Retained in Hotfix18 | Migration 000111; return action/calculator/models/components/views/routes | VERIFIED — NOT DEPLOYED |
| V1/D3 | Livewire-aware nested refund authorization directive repair without UI or authorization bypass | Hotfix18 release commit | `resources/views/pages/pos/index.blade.php` | VERIFIED — NOT DEPLOYED |
| I4 | Pre-Artisan `bootstrap/cache` runtime preparation, application-user Artisan execution, already-Ran 000111 acceptance and exact Hotfix16 rollback | Hotfix18 operator scripts | Promotion and activation scripts | VERIFIED — NOT DEPLOYED |

Production access, deployment, backup execution, migration execution, active-symlink modification, service operation, browser testing, tag, push, and remote contact are outside candidate preparation and remain unexecuted.
