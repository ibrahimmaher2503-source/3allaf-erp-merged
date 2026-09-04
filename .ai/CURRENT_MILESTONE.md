# Current Milestone

**Date:** 2026-09-05
**Status:** v0.1.22-hotfix13 is a focused D3 cash-drawer data-integrity correction from exact Hotfix12 commit `287693218aded875749b643e9427f63318088b05`.

The correction closes the raw UAT drawer-insertion gap, makes store ownership authoritative for drawer company context, validates company/branch/store consistency on every Eloquent drawer save, adds deterministic fail-closed legacy repair and database constraints, filters invalid drawers from shift opening, and replaces misleading context-related 404 behavior with localized validation. Existing POS authorization, accounting, concurrency, route, idempotency, duplicate-shift, and permission behavior is preserved.

Focused database-free verification passed. Production deployment remains unexecuted; exact Hotfix12 is the required prerequisite and rollback release for the prepared Hotfix13 flow.
