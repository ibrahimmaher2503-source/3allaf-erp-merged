# Current Milestone

**Date:** 2026-09-14
**Status:** v0.1.22-hotfix41 runtime repair, its single focused final verification, clean commits and immutable operator package are complete from exact Hotfix40 code commit `08d733692895f1f3bf3f5be70ed17f5c37b5ddb1`. Production application files remain on exact Hotfix39 `76e2f1fa780fb6da758a471902761ca6f40acbd8`, while migration 000114 is already `Ran` and retained.

Hotfix41 contains the complete Hotfix40 purchase-order approval/receiving lifecycle, concurrency-safe partial invoicing, stored localized PDFs, supplier-aware entry and centralized scoped exports. The repair is limited to scalar translation keys in directly related reporting views and fail-closed runtime permission handling.

Activation executes no migrations: it requires 000111, 000112, 000113 and the already-applied 000114 to be `Ran`. Promotion authenticates and verifies the complete manifest exactly once. Activation normalizes active/candidate cache permissions, renders authenticated `/reports` in `ar`, `en` and `ar-EG` before the atomic switch, performs one post-switch health check, and restores exact Hotfix39 application files on failure while retaining migration 000114.
