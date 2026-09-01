# v0.1.22-hotfix1 Post-Deployment Correction Evidence

**Date:** 2026-09-01  
**Branch:** `hotfix/v0.1.22-post-deployment-review`  
**Baseline:** tagged v0.1.22 commit `3a92ba2980eb26bca17a30b270d98af32b0645ce`  
**Release identity:** `0.1.22-hotfix1`

## Correction ledger

- Arabic: added missing residence, inventory, purchasing, filter-deletion, barcode-label, pricing-remediation, physical-size and enum labels; affected raw statuses use localized labels.
- Setup: a satisfied required or optional rule is `completed`; `ready` is no longer emitted as a second satisfied state. Progress and counters include required steps only.
- Product Filters: delete permission is enforced server-side; unused records delete, referenced values require same-group replacement, safe family-only removal, or archive; historical variant assignments cannot be detached. Replacement de-duplicates existing target assignments transactionally and every outcome is audited.
- Inventory Balances: reset clears every balance filter and returns the canonical balance URL while retaining authorization and server pagination.
- Quantities: Product Cards cannot enable fractional quantities. Purchase, inventory, returns and POS inputs/actions accept whole product units only; displays remove stored decimal padding. Monetary cost, price, tax, discount and payment precision remains unchanged. The diagnostic command is read-only.
- Barcode workspace: searchable bilingual product selection, actual active barcode selection, outlet/template/printer context, copies, preview and PDF output are implemented with bounded results and server authorization.
- Product Card pricing: average inventory cost and Base Consumer Price List 0 are explicitly separated; missing/zero selling price remediation targets the exact base-price field and barcode-only labels remain possible.
- Barcodes: EAN-13 checksum validation and standards-based EAN-13 guard/data patterns are rendered; printable ASCII Code 128-B uses start, weighted checksum, stop, quiet zones and human-readable text.
- Printing: persisted template layout includes physical mm width/height, 203/300 DPI profile, orientation, margin, gap, A4 rows/columns, symbology and visible fields. Exact 50 × 30 mm and 40 × 25 mm profiles are forced to their physical size; 58/80 mm thermal and A4 profiles are wired through preview/browser/PDF output. A4 uses a label grid; roll/label PDF uses the saved physical page box.
- Purchase Invoice cards: remaining quantity is explicitly labeled in whole units.
- Version: the application identity is canonically `0.1.22-hotfix1`.

## Verification actually completed

- One consolidated focused pass: **4 tests, 59 assertions, all passed** (`tests/Unit/V022Hotfix1PostDeploymentTest.php`).
- All changed/new PHP files passed PHP 8.4 syntax checks; Arabic/English JSON parsed (`5541` / `2360` keys); `git diff --check` passed.
- Barcode validation covered a valid `4006381333931` EAN-13 and `TJ-ABC-001` Code 128 result, SVG/bar structures, source checksum/pattern contracts, and invalid-input guards through focused assertions/source inspection.
- The preserved v0.1.22 printing matrix remains valid and was not repeated: Arabic/English × desktop/mobile × template library/printer profiles, **8 cases passed**, with correct direction, no overflow or mixed-language leakage, and 44 px controls.
- One allowed frontend build passed with Node `v20.20.2`, npm `10.8.2`, Vite `v8.2.0`. Manifest SHA-256: `cc095c1ecdfd4690ed5e500b18c78c63d74043ae0933e548c72f5111a92fdf78`.
- Migration delta: one additive nullable JSON column, `2026_09_01_000103_add_barcode_label_layout_to_print_templates.php`. The preceding isolated 96-migration MariaDB chain and rollback/remigrate evidence remains valid; this correction pass did not repeat database work. No migration was run against shared or production data.

## Remaining acceptance

Only physical-printer output remains: an operator must print and measure/scanner-check one sample on each configured thermal/label/A4 device class. This cannot be truthfully completed without the physical hardware.

## Safety boundary

Production runtime/database, shared data, active release symlink, tags, pushes, remotes and deployment/cutover were untouched. The release prepared from the final commit is immutable and links only `.env` and `storage` to the existing shared paths; neither target was read or mutated during preparation.
