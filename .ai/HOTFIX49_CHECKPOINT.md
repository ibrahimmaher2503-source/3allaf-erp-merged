# Hotfix49 Checkpoint

**Recorded:** 2026-09-27

**Worktree:** `/home/rajeh/worktrees/rajeh-erp-hotfix48`

**Branch:** `hotfix/v0.1.22-hotfix49-product-code-row-alignment`

**Application baseline:** deployed Hotfix48 commit `d59f5644dde57f14e5351194f3f3454ae1364d0b`

**Repository baseline:** Hotfix48 factual deployment commit `cc1dd2f90d06972009a643ed3dac5da5e5fd1c46`

**Dedicated verification database:** `toyjoy_hf49_verify_2aa6628094c5` (name recorded before creation or any destructive database command)

## Current state

- Worktree started clean and preserves the newer Hotfix48 deployment documentation.
- Product-row component now supports a screen-reader-only label with the same top spacing as sibling inputs; purchase-order full-page/modal rows and purchase-invoice create/edit rows opt into it.
- Generated local Product Card identities now use `<first four supplier-code digits>-<six-digit serial>` and a prefix-scoped row in the existing locked `global_product_code_sequences` table. Product/barcode collision checks remain inside the allocation transaction.
- Full Product Card and purchase-invoice inline previews/help/error handling use the new format. Supplier selection is still required; codes not starting with four digits fail with the localized domain validation message instead of a fallback.
- The single focused verifier passed 23 assertions in the recorded database, entirely inside a rolled-back transaction. It covered Blade/source row alignment, accessible labels, responsive RTL/LTR contracts, `0123-000001` leading-zero allocation, shared-prefix `0123-000002`, existing-code collision skip, saved-code preservation, invalid/missing supplier refusal, inactive drafts, immutability, and international barcodes. The first invocation stopped before the transaction on a verifier-only interpolated source literal; that literal was corrected and the same database resumed without reset. The final pass succeeded. The account is locked and no credentials are retained.
- Direct changed PHP/verifier syntax, locale JSON parsing, existing CSS utility presence, and diff hygiene passed. No Vite build was needed because no Vite-managed source or new utility changed.
- Commit, packaging, fresh backup/isolated restore, activation, and live checks have not yet run.
