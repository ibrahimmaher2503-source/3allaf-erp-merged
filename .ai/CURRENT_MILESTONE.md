# Current Milestone

**Date:** 2026-09-14
**Status:** v0.1.22-hotfix40 Customer final batch is implemented from exact active Hotfix39 commit `76e2f1fa780fb6da758a471902761ca6f40acbd8`. The focused database-free verification and exactly one Node 20/Vite 8.2.0 production build passed; production remains untouched.

The completed slice covers the purchase-order approval and receiving lifecycle, partial concurrency-safe invoice conversion, stored localized PDFs, supplier-aware compact product entry, and centralized permission-scoped exports. Existing approval, attachment, inventory ledger, invoice snapshot and export infrastructure is reused. Historical approved documents and ledger snapshots remain immutable.

Activation is permitted to run only migration 000114 after proving migrations 000111, 000112 and 000113 are `Ran`. Promotion authenticates the archive and verifies its complete manifest exactly once. Activation prepares caches once, performs minimal authenticated `ar`, `en`, and `ar-EG` smoke checks, switches atomically, and restores exact Hotfix39 application files on failure while retaining backward-compatible schema additions.
