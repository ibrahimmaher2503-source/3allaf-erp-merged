# Current Milestone

**Date:** 2026-09-07
**Status:** v0.1.22-hotfix18 is the corrective D3/I4 release candidate from exact Hotfix17 commit `6f47db7a62b38e8bac2aa9a4983a63611a8c17e4`.

The milestone retains Hotfix17's compact three-column POS, cart-contained order tabs, bounded customer/product lookup, scanner control, compact payment cards, wide cash/card/split checkout, full source-linked refund workflow, financial and inventory reversal controls, audit, idempotency, authorization, and Arabic/English presentation.

Hotfix18 repairs all compiler-confirmed Blade/PHP structural failures found by the mandated all-template pass: nested POS/checkout refund authorization, cart inline PHP assignments, and the PHP 8.5 purchasing-return print fallback. It also hardens operator runtime preparation so `bootstrap/cache` exists with exact runtime ownership and permissions before Artisan is invoked as the `rajeh` application user.

Exact active Hotfix16 remains the activation prerequisite and rollback target. Migration 000111 may already be Ran and is preserved; Hotfix18 adds no migration. Production access or mutation, deployment, backup execution, migration execution, active-symlink changes, service actions, browser testing, tags, pushes, and remotes remain prohibited during preparation.
