# Current Task - v0.1.22-hotfix31 purchase-invoice keyboard compact UI

**Date:** 2026-09-11
**Change class:** I4
**Source baseline:** exact Hotfix30 commit `05f67897402d918001fd4da6a9c3204a26358c6d`
**Branch:** `hotfix/v0.1.22-hotfix31-purchase-invoice-keyboard-compact-ui`
**Status:** Purchase-invoice and price-list source changes, one clean Node 20 production build, and the focused local verification pass are complete. Release commit and immutable packaging remain; promotion and activation remain unexecuted root-operator actions.

Hotfix31 reuses the Hotfix30 browser-side purchasing lookup in each purchase-invoice line, adds complete invoice-line keyboard progression and duplicate-submit protection, compacts invoice metadata/lines/totals/notes/actions, and compacts the price-list filters. Existing invoice validation, tax, currency, receiving, draft/posting/accounting, supplier price, scanner, authorization, and quick Product Card contracts remain unchanged.

No migration, production access/change, deployment, sudo/root execution, push, tag, package/browser installation, or unrelated redesign is authorized. Exactly one Node 20 production Vite build was run after frontend edits. Activation accepts only exact Hotfix30, requires migration 000111 already Ran, runs authenticated bilingual HTTP and compiled invoice browser-runtime checks before and after switching, and restores exact Hotfix30 after any post-switch failure.
