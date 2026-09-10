# Current Task - v0.1.22-hotfix26 supplier product lookup

**Date:** 2026-09-10
**Change class:** I4
**Source baseline:** exact Hotfix25 commit `510241c162056a305ce291286b5b0a4b22cb9518`
**Branch:** `hotfix/v0.1.22-hotfix26-supplier-product-lookup`
**Status:** Implementation and focused local verification are complete. Release commit and immutable operator packaging are the remaining local finalization steps; promotion and activation remain unexecuted operator actions.

Hotfix26 upgrades purchase-order, purchase-invoice, and supplier-return product entry to a shared supplier-aware bilingual combobox. It requires the supplier first, defaults to associated products, supports authorized all-product search, exact scanner selection, keyboard row progression, native opt-in camera scanning, confirmed supplier changes, authoritative posted supplier price lookup, and save-time supplier-product association.

The existing `product_suppliers` relation is sufficient, so no migration was added or run. Activation must accept only exact Hotfix25 commit `510241c162056a305ce291286b5b0a4b22cb9518`, prove it through its exact regular non-symlink `RELEASE_COMMIT`, validate optional identity metadata when present, require migration 000111 already Ran without executing migrations, preflight the purchasing runtime contract before switching, and roll back exactly to Hotfix25 on any failure.
