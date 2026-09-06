# Current Task — v0.1.22-hotfix16 POS checkout and multi-customer workflow

**Date:** 2026-09-06
**Change class:** D3 / I4
**Source baseline:** exact operator-verified Hotfix15 commit `68699be35880dc154519c4e293c1a32bb69ef967`
**Branch:** `hotfix/v0.1.22-hotfix16-pos-checkout-workflow`
**Production baseline:** owner supplied and not re-checked: version `0.1.22-hotfix15`, commit `68699be35880dc154519c4e293c1a32bb69ef967`, migration 000109 Ran, maintenance OFF, smoke PASS.
**Status:** Implementation, documentation, and focused verification complete in the isolated worktree. The local release commit, immutable archive, manifest, and unexecuted operator scripts remain.

Hotfix16 replaces the session-only POS cart with server-persisted open orders scoped to the exact company, branch, selling outlet, cash drawer, shift, cashier, and checkout token. It adds bounded customer search and in-POS customer creation, compact Arabic-first responsive cart/payment UX, cash/card/mixed payment portions, atomic completion through the existing sale/payment/inventory/accounting services, a stay-on-POS success modal, and scope-safe receipt profile resolution with browser-print fallback.

Migration 000110 adds durable open-order, line, and payment-draft tables plus two reversible customer search indexes. Existing sale, payment, inventory, tax, accounting, audit, shift, drawer, approval, rounding, receipt, and immutable-snapshot contracts remain authoritative.

The owner explicitly requested one consolidated focused Hotfix16 verification pass. The only automated test authorized for this task is `tests/Unit/V0122Hotfix16PosCheckoutWorkflowTest.php`, a database-free source-contract test for the named POS scope. No broad suite, browser control, live HTTP, database operation, production access, deployment, production backup, migration execution, tag, push, or remote contact is authorized.
