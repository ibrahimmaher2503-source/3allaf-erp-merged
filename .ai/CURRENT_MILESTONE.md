# Current Milestone

**Date:** 2026-09-14
**Status:** v0.1.22-hotfix37 Customer Notes Batch 1 is implemented and locally verified from exact active Hotfix36 commit `c5f6d57150a04d95915a72f5146a2aebd369bc83`; the immutable release commit and offline operator artifacts close the milestone.

The slice is limited to contextual help, sidebar navigation behavior/order, branch presentation, the existing per-user UI-preference architecture for default list filter visibility, and users/roles/permissions presentation. It adds no migration and changes no business records, permissions, authorization model, warehouse/POS relationship, purchasing, accounting, inventory calculation, or transactional behavior.

Release activation is optimized: promotion authenticates the archive and complete manifest once; activation checks exact identities, runtime links/permissions, already-Ran migration 000111, prepares caches once, runs one authenticated smoke render for each of `ar`, `en`, and `ar-EG` before switching, performs an atomic switch, and runs one minimal health check. Any post-switch failure restores exact Hotfix36.
