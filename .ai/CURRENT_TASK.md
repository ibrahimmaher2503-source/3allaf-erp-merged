# Current Task — v0.1.22-hotfix17 POS compact redesign and refund workflow

**Date:** 2026-09-06
**Change class:** D3 / I4
**Source baseline:** exact operator-verified Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`
**Branch:** `hotfix/v0.1.22-hotfix17-pos-compact-refunds`
**Production baseline:** owner supplied and not re-checked: version `0.1.22-hotfix16`, commit `31883f5c823458d30738708ec96e1d02337f4c9b`, migration 000110 Ran, maintenance OFF, smoke PASS.
**Status:** Implementation complete in the isolated Hotfix17 worktree; one owner-authorized consolidated database-free verification pass, release commit, and immutable operator package remain.

Hotfix17 compacts the desktop POS while preserving Hotfix16 durable exact-scope open orders, customer switching, product/cart approvals, cash/card/mixed settlement, Hotfix14 rounding, atomic checkout, success, and receipt routing. Open-order tabs now live in the cart header; customer/product search is icon-only and automatic/server-bounded; payment choices are compact cards; and checkout uses a wide responsive cash/card/split modal.

The in-POS refund wizard verifies a visible posted source sale, reserves remaining refundable whole quantities, records condition and an authorized same-company/branch inventory return location, defaults to safe original-payment allocations, requires an active exact-outlet shift/drawer for every cash refund portion, preserves independent approval, and posts inventory plus immutable refund settlements atomically under source-sale locking and completion idempotency. Migration 000111 extends the existing return tables with reversal values, location, refund shift/drawer, completion idempotency, rejection evidence, safe payment snapshots, and an immutable financial reversal snapshot.

The owner authorized one final focused database-free pass: changed PHP syntax, changed Blade compilation, POS/refund route and source-contract checks, Arabic/English JSON parity, `git diff --check`, and Vite only if frontend input assets changed. Broad tests, browser control, databases, production, deployment, backup execution, migration execution, tags, pushes, and remote contact remain prohibited.
