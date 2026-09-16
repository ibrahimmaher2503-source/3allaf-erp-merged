# Current Milestone

**Date:** 2026-09-16
**Status:** Root reports schema-after PASS on the retained isolated post-000116 database. Application candidate `be76a45db4e6c4d7eb48c5ecd447a811733ebf81` fixes strict decimal argument typing during PO-linked invoice creation. Remaining scenario conversions, locale renders and Hotfix41 compatibility require authenticated scenarios-only root execution. Earlier immutable packages remain preserved; exact Hotfix41 remains production. No promotion/activation occurred; original numbered coverage remains unproven.

Hotfix42 completes the customer-correction scope carried by Hotfix37–Hotfix41 and adds only the two owner-approved migrations: 000115 for purchase-order line financial snapshots and 000116 for nullable incomplete-draft product codes. Neither migration has been executed locally. Activation must accept exact Hotfix41, require 000111–000114 already `Ran`, run only 000115 then 000116, validate schema/data invariants, smoke authenticated `ar`, `en`, and `ar-EG`, switch atomically, and restore exact Hotfix41 application files on failure while retaining both schema changes and their data.

Deferred C remains deferred because the referenced pair is unidentified. A branch may contain both warehouses and sales outlets; legitimate scope, relationship, and active POS-mapping validation remain intact.
