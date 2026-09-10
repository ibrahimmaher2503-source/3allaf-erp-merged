# Current Task - v0.1.22-hotfix27 purchase-order full-page search

**Date:** 2026-09-10
**Change class:** I4
**Source baseline:** exact Hotfix26 commit `2aae45258b2ebc0ef71b140567397990cea74b63`
**Branch:** `hotfix/v0.1.22-hotfix27-purchase-order-full-page-search`
**Status:** Implementation and focused local verification are complete. Release commit and immutable operator packaging are the remaining local finalization steps; promotion and activation remain unexecuted operator actions.

Hotfix27 corrects the shared purchasing lookup input geometry for RTL/LTR and mixed numeric text, keeps results live while typing, matches contained identifiers and bilingual names, and requires explicit highlighting before Enter when multiple results exist. Unique exact barcode, SKU, supplier code, or model matches retain immediate scanner selection. Purchase-order creation now has a permission-gated full-page route and adjacent sidebar action while reusing the existing Livewire form state and save action.

No migration was added or run. Activation must accept only exact Hotfix26 commit `2aae45258b2ebc0ef71b140567397990cea74b63`, prove its regular non-symlink `RELEASE_COMMIT`, validate optional `RELEASE_IDENTITY` when present, require migration 000111 already Ran without executing migrations, preflight the bilingual lookup/full-page route/runtime contract, and roll back exactly to Hotfix26 on any failure.
