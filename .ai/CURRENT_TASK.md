# Current Task — v0.1.16 Setup and Administration Foundation

**Date:** 2026-08-29
**Branch:** `feature/v0.1.16-setup-admin-foundation`
**Baseline:** published `v0.1.15` commit `4b385aa081d390c9a5ba3620072423f1e7f46c6c`

## Scope

- One authoritative, permission-filtered 21-step Setup sequence: 5 Basics, 5 Settings, 11 Operational Readiness.
- Persistent direct-route Setup context, factual readiness reasons, and append-only company-scoped skip/defer decisions.
- Separate Save and navigation controls in Settings; exact loading targets and no implicit save on navigation.
- Supplier readiness based only on an active supplier with an active financial-settlement method.
- Idempotent least-privilege Cashier role template using existing permissions and existing location scopes.
- Shared Save labels on representative create/edit forms; route-specific list actions remain unchanged.

## Verification directive

Focused unit coverage is owner-authorized for sequence, continuation decisions, direct-route resolution, and Cashier boundaries. The owner-authorized genuine browser pass completed against the loopback-only guarded renderer with task-local writable runtime paths and the shared environment referenced in place. Database access was GET/HEAD-only, session-transaction read-only, write-statement rejected, and rolled back at request termination.
