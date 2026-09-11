# Current Task - v0.1.22-hotfix32 invoice verification fail-closed repair

**Date:** 2026-09-11
**Change class:** I4
**Source baseline:** exact Hotfix31 commit `3ca22dbe6671f94aaec4132b84fb6a2c126cec82`
**Branch:** `hotfix/v0.1.22-hotfix32-invoice-verification-fail-closed`
**Status:** The smallest verifier and fail-closed activation correction and focused local verification are complete in a separate clean worktree. Release commit and immutable packaging remain; promotion and activation remain unexecuted root-operator actions.

Hotfix32 replaces the stale supplier-label substring assertion with a rendered editor-control check, makes runtime verification explicitly exit non-zero on every exception, requires an exact terminal PASS marker, preserves `tee` pipeline statuses, and rolls back exact Hotfix31 after any post-switch failure. Hotfix31 purchase-invoice, price-list, lookup, scanner, keyboard, and Hotfix30 purchase-order behavior remain unchanged.

No migration, production access/change, deployment, sudo/root execution, push, tag, package/browser installation, frontend edit, or unrelated redesign is authorized. Vite build count is zero. Activation accepts only exact Hotfix31, requires migration 000111 already Ran, runs authenticated bilingual HTTP and compiled invoice browser-runtime checks before and after switching, and restores exact Hotfix31 after any post-switch failure.
