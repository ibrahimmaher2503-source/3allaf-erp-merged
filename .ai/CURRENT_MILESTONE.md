# Current Milestone

**Date:** 2026-09-14
**Status:** v0.1.22-hotfix38 Customer Notes Batch 2 is complete in development from exact active Hotfix37 commit `0f0e7aa18d47986f6805c89b3494f4f55042e151`; focused verification, the release commit, and offline operator preparation are complete. Production remains untouched.

The slice is limited to payment-method, tax, product-tax assignment, document-numbering, and printer/template settings. Existing authorization, print resolution, stored configurations, transactional tax snapshots, accounting calculations, and historical documents remain unchanged.

The only schema addition is the owner-authorized nullable/indexed `products.tax_setting_id` foreign key with `nullOnDelete`. Promotion verifies the complete manifest exactly once. Activation accepts only exact Hotfix37, checks 000111 is already `Ran`, executes only migration 000112 when pending (or accepts its retained `Ran` state after an earlier application rollback), confirms it is `Ran`, prepares caches once, performs one authenticated pre-switch render for each locale, switches atomically, and performs one post-switch health check. A post-switch failure restores exact Hotfix37 application files without dropping the backward-compatible nullable column.
