# Current Task — Hotfix43 Original Requirements Correction and Acceptance

**Date:** 2026-09-18

**Change class:** I4

**Source baseline:** deployed Hotfix42 commit `cb8c1f9637370e531f6ab23f634e70784742cf35`, tree `2b43448ebd232734d4b701eedc78720e33e8fb4d`

**Branch:** `hotfix/v0.1.22-hotfix43-original-requirements-corrections`

**Worktree:** `/home/rajeh/worktrees/rajeh-erp-hotfix43`

**Status:** Completed and deployed. Production serves exact Hotfix43 candidate `c71d0ad0651e62aa30c537e0f75fb8fdc6b4d8cf`; fresh encrypted backup/isolated restore, migrations 000117/000118, atomic activation and live verification passed.

Implement every concrete defect identified by the recovered 73-row Hotfix42 audit and owner corrections, then obtain missing acceptance evidence without treating every prior PARTIAL row as broken code. Preserve the known inventory exception without modifying stock.

Current completed source work covers branch/type numbering enforcement and saved-reference presentation; export tenant/filter/completeness corrections; opening paste and zero-opening safeguards; Customers/printer navigation; compact print-template and customer-group UI; product toolbar/help cleanup; loyalty help; supplier English-name enforcement, structured contacts and company-scoped latest-cost fallback; purchase-order return/review responsibility/history; explicit retryable PDF outcomes; and related translations.

Approved schema scope is limited to:

1. `purchase_invoice_lines.tax_inclusive BOOLEAN NOT NULL DEFAULT FALSE` to retain the immutable inclusive/exclusive line snapshot through conversion, save, reload and edit.
2. `tax_settings.company_id BIGINT UNSIGNED NOT NULL` with company foreign key and company/code uniqueness to make product/default tax resolution company-owned.

Migrations 000117 and 000118 and all affected company-scoped tax consumers are implemented and deployed. Production records 000117 in batch 24 and 000118 in batch 25. The isolated MariaDB/browser pass, all 12 shared XLSX structural checks, localized PDF generation, final 84-row matrix, fresh encrypted production backup/restore reconciliation, live health and read-only authenticated affected-page smoke are complete. Microsoft Excel and physical printer/scanner acceptance remain explicitly external.

The unresolved owner decisions remain: cross-branch receiving; whether globally unique `CUS-*` plus company visibility satisfies the customer-code contract; whether document timezone fields are distinct from global timezone configuration; and the unidentified deferred comparison C.
