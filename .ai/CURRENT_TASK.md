# Current Task — v0.1.22-hotfix15 Complete Batch

**Date:** 2026-09-05
**Change class:** D3 / I4 (includes the explicitly closed V0/V1 UI batch)
**Source baseline:** exact Hotfix14 commit `32c13a8e54f8dad45691a88318f9b475ff944f15` (tree `141e620ef70ab92095893e36a6182d6c6433250a`)
**Branch:** `hotfix/v0.1.22-hotfix15-complete-batch`
**Production baseline:** operator-verified active release `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix14-pricing-cash-rounding`, commit `32c13a8e54f8dad45691a88318f9b475ff944f15`, tree `141e620ef70ab92095893e36a6182d6c6433250a`; migrations 000104–000108 Ran, maintenance OFF, HTTPS smoke passed.
**Status:** All four requested Hotfix15 scopes are implemented. One focused database-free verification pass, final records, commit, and immutable local release preparation remain; deployment is not authorized.

Hotfix15 closes the owner-directed batch without reducing scope: shared UI/navigation cleanup and exact Arabic workflow labels; dynamic company-scoped XLSX import workbooks; scalable product-to-location assignment and opening-inventory draft/import/approval/reversal workflows; and guarded product archive/dependency-free deletion with separate unexecuted QA/UAT audit and cleanup commands.

The new migration creates only the product/location assignment configuration table. Opening quantities remain separate immutable inventory documents whose costs come from Product Cards and whose approval/reversal effects use the stock ledger, transactions, locks, idempotency keys, scope guards, and audit records. Product removal enumerates database dependencies and fails closed; operational or historical dependencies prohibit physical deletion.

Automated suites and browser control remain prohibited for this task. Verification is limited to the requested database-free syntax, Blade compilation, locale, route-source, workbook structure/round-trip, focused source contracts, and final diff hygiene. No database, migration, seeder, QA audit, cleanup, production access, deployment, tag, push, or remote operation is authorized.
