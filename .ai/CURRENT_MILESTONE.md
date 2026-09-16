# Current Milestone

**Date:** 2026-09-16
**Status:** Replacement candidate `7263bb5949fc5c456d4f3e0e34d8c5d74dd102d7` corrects the packaged schema guard by checking SQL NULL through `COLUMN_DEFAULT IS NULL`, independently of MariaDB's returned default representation. Earlier Hotfix42 packages are immutable and must not be activated; the replacement retains exact Hotfix41 `e4ac8a0a98d788eabcb750c797307fee0aa2efef` as the production baseline. Promotion and activation remain unexecuted and blocked pending guard-after-only isolated runtime checks.

Hotfix42 completes the customer-correction scope carried by Hotfix37–Hotfix41 and adds only the two owner-approved migrations: 000115 for purchase-order line financial snapshots and 000116 for nullable incomplete-draft product codes. Neither migration has been executed locally. Activation must accept exact Hotfix41, require 000111–000114 already `Ran`, run only 000115 then 000116, validate schema/data invariants, smoke authenticated `ar`, `en`, and `ar-EG`, switch atomically, and restore exact Hotfix41 application files on failure while retaining both schema changes and their data.

Deferred C remains deferred because the referenced pair is unidentified. A branch may contain both warehouses and sales outlets; legitimate scope, relationship, and active POS-mapping validation remain intact.
