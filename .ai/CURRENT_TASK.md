# Current Task - v0.1.22-hotfix28 purchase-order runtime repair

**Date:** 2026-09-10
**Change class:** I4
**Source baseline:** exact Hotfix27 commit `40439ebc3a7e87fb96255addc7796d3c1ef16c8c`
**Production baseline:** exact active Hotfix27 path `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix27-40439ebc3a7e`
**Branch:** `hotfix/v0.1.22-hotfix28-purchase-orders-runtime-repair`
**Status:** Runtime contract repair and focused local verification are complete. Release commit and immutable operator packaging remain local finalization steps; promotion and activation remain unexecuted operator actions.

Both production request IDs `249d9a80-6b78-4a9b-a7df-10aadbb26c8e` and `df115f08-bc9c-425d-a9d8-fa71cd3ed1b2` failed because `purchasing/orders.blade.php` referenced `$createPage` while the custom Livewire `render()` data arrays omitted it. Hotfix28 supplies that contract in both overview and create branches without restoring the create modal. It adds a production-shaped authenticated verifier that renders both routes in Arabic and English with an existing authorized user/store, array-only cache/session state, warning-to-exception handling, and an always-rolled-back database transaction.

No migration was added or run. Activation must accept only exact Hotfix27, require migration 000111 already Ran, verify both authenticated routes before switching, switch to Hotfix28, repeat both checks, and roll back exactly to Hotfix26 on any activation failure.
