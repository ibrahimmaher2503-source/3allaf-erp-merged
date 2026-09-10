# Current Milestone

**Date:** 2026-09-10
**Status:** v0.1.22-hotfix26 is the I4 release-candidate batch from exact Hotfix25 commit `510241c162056a305ce291286b5b0a4b22cb9518`.

The bounded batch replaces purchasing product selectors with one reusable supplier-aware searchable combobox while preserving Hotfix25 behavior outside purchasing and all existing purchasing authorization, source-document, unit, tax, total, inventory, and approval rules. The existing many-to-many supplier-product relation and approved purchase-invoice history are reused; no schema change is required.

Focused PHP/Blade/JavaScript/runtime/locale checks and exactly one Node 20 Vite production build passed. The database-backed feature contract was created but could not be executed because this isolated worktree has no authorized MariaDB credentials; no database was accessed or changed. Operator promotion/activation remains external and fail-closed.
