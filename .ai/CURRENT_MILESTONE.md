# Current Milestone

**Date:** 2026-09-16
**Status:** Replacement candidate `9f0f9f9303c2362e7e5f8125d51eb493cef7083d` corrects the packaged guard using engine-aware normalization of the confirmed MariaDB unquoted NULL token while rejecting quoted SQL literals. Earlier Hotfix42 packages remain immutable and superseded; exact Hotfix41 `e4ac8a0a98d788eabcb750c797307fee0aa2efef` remains the production baseline. Promotion and activation remain unexecuted pending guard-after and subsequent isolated runtime checks.

Hotfix42 completes the customer-correction scope carried by Hotfix37–Hotfix41 and adds only the two owner-approved migrations: 000115 for purchase-order line financial snapshots and 000116 for nullable incomplete-draft product codes. Neither migration has been executed locally. Activation must accept exact Hotfix41, require 000111–000114 already `Ran`, run only 000115 then 000116, validate schema/data invariants, smoke authenticated `ar`, `en`, and `ar-EG`, switch atomically, and restore exact Hotfix41 application files on failure while retaining both schema changes and their data.

Deferred C remains deferred because the referenced pair is unidentified. A branch may contain both warehouses and sales outlets; legitimate scope, relationship, and active POS-mapping validation remain intact.
