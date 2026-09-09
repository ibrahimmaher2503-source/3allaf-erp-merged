# Current Task - v0.1.22-hotfix24 product quick-create and compact UI

**Date:** 2026-09-09
**Change class:** I4
**Source baseline:** exact Hotfix23 commit `ac812449e446f69301a0202cb540c625f71eb9d8`
**Branch:** `hotfix/v0.1.22-hotfix24-product-quick-create-compact-ui`
**Status:** Implementation, the one clean production Vite build, focused final verification, release commit, and immutable operator packaging are complete. Operator promotion/activation remain unexecuted.

Hotfix24 completes the existing purchase-invoice quick Product Card path with the Phase 1 identity, classification, and commercial fields; server authorization and duplicate validation; one atomic catalog/barcode transaction; selected-supplier prefill; and insertion into the in-memory draft without posting the invoice or replacing its entered state.

The release also changes only the Arabic presentation label for product cost to `سعر التكلفة`, moves the two optional product descriptions from Basic Identity to Stage B, compacts shared authenticated page framing/header spacing, removes the duplicated Reports catalog while retaining one permission-filtered selector and all filters/exports, and fills the affected report localization gaps. Hotfix23 sidebar geometry/restoration and POS/payment/refund behavior are preserved.

No migration was added or run. Activation must accept only exact Hotfix23 commit `ac812449e446f69301a0202cb540c625f71eb9d8`, require migration 000111 already Ran without executing migrations, preflight runtime caches/rendering/packaged assets before switching, and roll back exactly to Hotfix23 on any failure.
