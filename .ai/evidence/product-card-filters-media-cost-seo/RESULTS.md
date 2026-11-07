# Product-card hotfix evidence — 2026-10-02

Branch: `hotfix/product-card-filters-media-cost-seo`; worktree `/tmp/rajeh-erp-product-card-filters-media-cost-seo`; source `c41c5817c76d4f69a42b1888ebe545a3c51bad9d`. Class I4: narrow catalog changes plus additive assignment migration 000119. No merge or deployment.

## Findings

The optional card fields used older lookup tables while Product Filters management owns ProductOptionGroup/ProductOptionValue. Import stored descriptive filters in variant-family pivots. The card now uses that existing configuration library with independent product_filter_values assignments. Migration 000119 copies only ordinary-product selections, preserves every legacy/variant row and refuses destructive down with saved assignments. Variant generation and existing matrix persistence are unchanged; ordinary cards no longer mount Variations. Import/deletion dependencies use the new pivot.

Required cost was validated before loading/locking an existing product. Blank/omitted input now retains the locked saved cost; unauthorized forged replacement is ignored, authorized intentional changes work, zero is valid, and genuinely missing final cost still fails. The four SEO meta controls/payload fields are omitted; the action preserves existing SEO data when omitted. Short/full bilingual descriptions remain.

The null-upload guard falsely claimed a pre-Laravel rejection. A null upload can mean no file or incomplete transfer. Upload controls now wait for completion, reset readiness after success and report an accurate message. Media roles and protected attachment validation are unchanged.

## Checks actually run

- Changed PHP syntax and the three changed Blade compilations/compiled syntax passed; form compilation was repeated only after its filter relation changed. Locale JSON parsing passed.
- A generated 98,709-byte PNG passed Livewire temporary upload, completion on the actual product component, snapshot serialization/hydration and protected attachment MIME/signature validation.
- verify-persistence-contracts.php passed 22 checks. Its runtime cost checks execute the actual action's bounded preparation block with in-memory inputs: omitted/blank saved cost, intentional replacement, denied forged replacement, saved zero and missing cost. Other assertions are explicitly filter/SEO source contracts, not database save/reload evidence.
- The owner explicitly authorized local browser control after automatic review rejected the initial attempt. An isolated loopback Laravel/Flux fixture used actual before/after media markup and an existing Chromium binary. Normal 98,709-byte uploads returned HTTP 200 for _startUpload, multipart, _finishUpload and the fixture save action in both versions, without JavaScript errors. The old button could reach the null guard without a multipart request. The changed button stayed disabled without a file and during a deliberately delayed transfer. Both additional/main roles reached the fixture action, and success cleared readiness. The fixture does not call ManageProductMediaAction or write database records.
- Read-only recorded-release evidence showed two 98,709-byte temporary files, proving reported-size files reached Laravel. Inspected PHP defaults were 2 MB/file and 8 MB/request; Livewire temporary validation is 12 MB and protected product-image policy remains 8 MB. No limit was raised. Effective web/pool logs were unavailable; the exact original production request failure was not independently reproduced or attributed to a server limit.

## Unexecuted checks

XAMPP MariaDB is unavailable here. Migration up/backfill/down, actual product save/reload, filter SQL persistence, SEO database retention and gallery database writes were NOT RUN. Run these focused checks in the approved disposable XAMPP environment before integration/release. No full suite, production browser control/mutation, migration execution, Vite build, installation, merge or deployment occurred. Final CodeGraph review and commit/push are recorded in the session summary.

## Changed files

- app/Modules/Catalog/Actions/DeleteProductOptionAction.php
- app/Modules/Catalog/Actions/ImportProductCardsWorkbookAction.php
- app/Modules/Catalog/Actions/SaveProductAction.php
- app/Modules/Catalog/Models/Product.php
- app/Modules/Catalog/Services/ProductDependencyService.php
- resources/views/catalog/product-form.blade.php
- resources/views/catalog/product-options.blade.php
- resources/views/catalog/product-variations.blade.php
- database/migrations/2026_10_02_000119_create_product_filter_values.php
- lang/ar.json
- lang/ar-EG.json
- lang/en.json
- docs/36-module-data-contracts.md
- .ai/CURRENT_TASK.md
- .ai/SESSION_SUMMARY.md
- .ai/TEST_RESULTS.md
- .ai/evidence/product-card-filters-media-cost-seo/RESULTS.md
- .ai/evidence/product-card-filters-media-cost-seo/verify.php
- .ai/evidence/product-card-filters-media-cost-seo/verify-persistence-contracts.php
