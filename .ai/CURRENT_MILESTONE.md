# Current Milestone

**Date:** 2026-09-14
**Status:** v0.1.22-hotfix39 Customer Notes Batch 3 is complete in development from exact active Hotfix38 commit `45d9e78c757cd83a596b11d613475dbdc441d2c3`; focused verification, release commit, and offline operator preparation are complete. Production remains untouched.

The slice is limited to product catalog UX/search/supplier identity/draft readiness, opening inventory entry/import/document context, customer/group UX, and supplier/group/contact UX. Existing exports remain routable for a future Reports Center, payment terms remain stored and accepted by backend actions, branch pricing continues through the existing matrix/resolver contracts, and posted inventory/accounting history remains immutable.

Activation is permitted to run only migration 000113 after proving migrations 000111 and 000112 are `Ran`. Promotion authenticates the archive and verifies its complete manifest exactly once. Activation prepares caches once, performs authenticated `ar`, `en`, and `ar-EG` focused renders, switches atomically, and restores exact Hotfix38 application files on failure while retaining the nullable schema additions.
