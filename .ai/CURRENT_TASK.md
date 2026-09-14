# Current Task — v0.1.22-hotfix40 Purchase Workflow and Reports Center

**Date:** 2026-09-14
**Change class:** Customer final batch hotfix release
**Source baseline:** exact active Hotfix39 commit `76e2f1fa780fb6da758a471902761ca6f40acbd8`
**Branch:** `hotfix/v0.1.22-hotfix40-purchase-workflow-reports-center`
**Status:** Complete in development; focused verification and the one required Vite production build passed. Release commit and immutable offline packaging are the remaining closure steps.

Hotfix40 completes the controlled purchase-order lifecycle, full-page draft editing, branch-aware numbering/counts, independent receiving progress, partial multi-invoice conversion with remaining-quantity locking, compact supplier-cost-aware product lines, private versioned purchase-order PDFs, and the permission-scoped Reports and Export Center. Distributed list exports and payment terms remain hidden from UI only; their backend data and services remain available.

The owner-authorized migration is `2026_09_14_000114_extend_purchase_orders_and_exports_for_hotfix40.php`. It adds purchase-order receiving state and its workflow index, private purchase-order document metadata, and nullable indexed export request hashes. Its `down()` intentionally retains these backward-compatible additions. No local migration execution is authorized. Activation must prove 000111, 000112 and 000113 are already `Ran`, run only 000114 if pending, and retain its additions if application rollback restores exact Hotfix39.
