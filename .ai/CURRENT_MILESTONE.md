# Current Milestone

**Date:** 2026-09-10
**Status:** v0.1.22-hotfix27 is the I4 release-candidate batch from exact Hotfix26 commit `2aae45258b2ebc0ef71b140567397990cea74b63`.

The bounded batch repairs purchasing lookup geometry and multi-match keyboard behavior, and moves purchase-order creation from a modal trigger to a dedicated responsive route with the same authorization and business logic. Supplier filtering, authorized scope, supplier prices, camera/external scanning, row progression, totals, validation, draft persistence, and unrelated purchasing screens remain on the Hotfix26 contracts.

Focused PHP/Blade/JavaScript/runtime/route/locale checks and exactly one clean Node 20 Vite production build passed. The dedicated database-backed feature test was updated but could not run because the isolated worktree has no authorized MariaDB credentials; its attempt failed before fixtures or database changes. Operator promotion/activation remains external and fail-closed.
