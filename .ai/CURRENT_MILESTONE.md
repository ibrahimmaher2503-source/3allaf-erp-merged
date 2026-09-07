# Current Milestone

**Date:** 2026-09-07
**Status:** v0.1.22-hotfix19 is the verified corrective I4 release candidate from exact Hotfix18 commit `c0fa3ed3623f7c259ba2c29109c08184bfa42eb4`; host-user runtime proof is enforced by the fail-closed activation preflight.

The milestone retains the Hotfix17 compact three-column POS, durable order tabs, bounded customer/product lookup, scanner control, compact payment cards, wide cash/card/split checkout, source-linked refund workflow, financial and inventory reversal controls, audit, idempotency, authorization, and Arabic/English presentation, plus the Hotfix18 Blade/PHP compiler repairs.

Hotfix19 gives the conditionally displayed refund wizard one permanent Livewire root without removing or changing any redesigned POS/refund function. It also corrects extracted-release runtime preparation: bootstrap cache is created after extraction with mode `0775`, recursively owned by `rajeh:rajeh`, checked writable as `rajeh` before and after cache generation, and every Artisan invocation remains under `runuser` as `rajeh`.

Exact active Hotfix16 remains the activation prerequisite and rollback target. Migration 000111 is required to remain already Ran; Hotfix19 adds no migration and its activation script does not run migrations. Production access or mutation, deployment, backup execution, migration execution, active-symlink changes, service actions, browser testing, tags, pushes, and remotes did not occur during preparation.
