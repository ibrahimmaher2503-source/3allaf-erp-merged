# Current Task — Hotfix49 Product-Code and Purchasing Row Corrections

**Date:** 2026-09-27

**Change class:** I4

**Source baseline:** deployed Hotfix48 application commit `d59f5644dde57f14e5351194f3f3454ae1364d0b`; post-deployment documentation commit `cc1dd2f90d06972009a643ed3dac5da5e5fd1c46` is preserved.

**Branch:** `hotfix/v0.1.22-hotfix49-product-code-row-alignment`

**Worktree:** `/home/rajeh/worktrees/rajeh-erp-hotfix48`

**Status:** Implementation and the single focused check are complete; candidate commit and deployment are pending. Purchase-order and purchase-invoice product-row labels are screen-reader-only while the desktop column headings, accessible labels, aligned inputs, responsive grid, and RTL/LTR behavior remain. Generated local Product Card codes use the first four digits of the selected supplier code plus a hyphen and a six-digit prefix-shared sequence through the existing locked sequence table and product/barcode collision checks.

No migration or frontend build is required. Existing item codes, international barcodes, inactive drafts, final-code immutability, and supplier English-name requirements remain unchanged. The focused rollback-only check passed in recorded MariaDB `toyjoy_hf49_verify_2aa6628094c5`; its account is locked and no credentials were retained. Deployment must use the established fresh encrypted backup, isolated reconciliation, atomic activation, and recovery process.
