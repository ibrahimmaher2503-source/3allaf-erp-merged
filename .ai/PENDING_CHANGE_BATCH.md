# Pending Change Batch

**Status:** HOTFIX19 PREPARED — FINAL VERIFICATION BLOCKED — NOT DEPLOYED
**Scope:** v0.1.22-hotfix19 POS Livewire root and extracted-release cache repair, retaining all Hotfix17/18 work
**Opened after:** operator-verified rollback to exact `v0.1.22-hotfix16`
**Source baseline:** exact Hotfix18 commit `c0fa3ed3623f7c259ba2c29109c08184bfa42eb4`
**Production baseline:** owner-confirmed: exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`, migration 000111 already Ran, maintenance OFF.
**Baseline disposition:** Hotfix16 remains active and is the exact Hotfix19 activation prerequisite and rollback target. It was not rechecked.

Do not clear or archive this ledger until an authorized operator confirms successful Hotfix19 production activation and smoke verification.

## Pending Changes

| Classification | Short description | Commit | Affected files | Status |
|---|---|---|---|---|
| V1/D3 | Full Hotfix17 compact POS, checkout, customer/product/scanner, order-tab and payment-card redesign | Retained in Hotfix19 | Hotfix17 POS Livewire/components/views | VERIFIED — NOT DEPLOYED |
| D3/I4 | Full Hotfix17 source-linked refund, inventory/payment/accounting/authorization/audit/idempotency and receipt workflow | Retained in Hotfix19 | Migration 000111; return action/calculator/models/components/views/routes | VERIFIED — NOT DEPLOYED |
| V1/D3 | Hotfix18 Blade/PHP compiler repairs without UI or authorization bypass | Retained in Hotfix19 | POS page/cart/checkout and purchasing-return print Blade templates | VERIFIED — NOT DEPLOYED |
| V1 | Permanent closed-state Livewire root for the retained refund wizard | Hotfix19 release commit | `resources/views/livewire/pos/refund-wizard.blade.php` | IMPLEMENTED — FINAL VERIFICATION BLOCKED |
| I4 | Post-extraction bootstrap cache creation, recursive `rajeh:rajeh` ownership, mode `0775`, pre/post cache-generation writability, application-user Artisan, already-Ran 000111 preservation and exact Hotfix16 rollback | Hotfix19 operator scripts | Promotion and activation scripts | IMPLEMENTED — FINAL VERIFICATION BLOCKED |

Production access, deployment, backup execution, migration execution, active-symlink modification, service operation, browser testing, tag, push, and remote contact are outside candidate preparation and remain unexecuted.
