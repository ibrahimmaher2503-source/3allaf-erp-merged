# Current Task — v0.1.22-hotfix14 Pricing and POS Cash Rounding

**Date:** 2026-09-05
**Change class:** D3 / I4
**Source baseline:** exact Hotfix13 commit `1c0519dc57b9c5b9af1aa6963dbbb448fce56bbe` (tree `b46016b660a560dad786d22018c38243efc9a8a9`)
**Branch:** `hotfix/v0.1.22-hotfix14-pricing-cash-rounding`
**Status:** Scoped source implementation and the one focused database-free verification pass are complete; immutable release preparation remains.

The canonical percentage-derived price resolver retains BC Math arithmetic and now names its approved 5 EGP increment explicitly. Only non-base automatically derived values use ceiling-to-5; Product Card base prices and effective manual overrides remain verbatim, and no existing price, sale, invoice, accounting, cost, or inventory record is recalculated.

Migration `2026_09_05_000108_configure_pos_cash_rounding_denomination` appends the owner-approved `5.0000` value to the canonical versioned POS setting only when that value is not already effective. It never rewrites or deletes setting history. POS cash settlement retains nearest-denomination arithmetic while non-cash-only settlement retains the exact invoice total. Missing or invalid configuration disables cash with one localized user-safe message instead of an unhandled rendering or checkout exception.

Hotfix13 drawer integrity, authorization, company/store scope, price precedence, taxes, discounts, promotions, accounting, inventory, shift lifecycle, locking, idempotency, audit behavior, and posted-document immutability remain unchanged. Pending UI/navigation/permission/printer/initial-setup work is excluded. Production deployment and mutation are not authorized in this worktree.
