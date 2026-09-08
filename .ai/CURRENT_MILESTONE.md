# Current Milestone

**Date:** 2026-09-08
**Status:** v0.1.22-hotfix22 is the verified I4 release candidate from exact Hotfix21 source commit `63d542558fc301f7b6554da5c5747ca2f4124a46`, targeting exact production Hotfix20 commit `04d09977abd9b3d56bc83fd067a78642f5127ca7`.

The release preserves Hotfix20 and Hotfix21 POS/refund/payment/Livewire-root/Vite/cache/sidebar/accounting/inventory/permission/idempotency behavior. It corrects the navigation runtime failure by normalizing all optional renderer fields and assigning stable fallback keys before Blade access. Runtime contracts cover the real keyless Initial Setup item, permission-filtered cashier/manager/administrator profiles, nested groups, headings, separators, empty fields, and the authenticated dashboard layout.

Exactly one Vite production build produced Hotfix22 assets. Operator activation remains external and fail-closed: exact Hotfix20 is the only accepted active baseline and rollback target, migration 000111 must already be Ran, no migration command is allowed, and the `rajeh` navigation/cache/asset preflights must pass before switching.
