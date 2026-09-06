# Current Milestone

**Date:** 2026-09-06
**Status:** v0.1.22-hotfix17 is the D3/I4 compact POS and source-linked refund release candidate from exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`.

The milestone preserves the deployed Hotfix16 multi-order checkout contract and provides a compact three-column desktop POS, cart-contained order tabs, automatic bounded customer lookup, unobstructed product/barcode search, compact payment cards, wide cash/card/split checkout, corrected cash remaining/change labels, and compact success/receipt controls.

It also provides the missing in-POS refund workflow: source verification, full/partial remaining-quantity enforcement, condition and authorized inventory return location, original/cash/store-value refund selection, safe split allocation against immutable source payments, independent approval/rejection, source-sale serialization, completion idempotency, exact-outlet cash shift/drawer enforcement, reversing inventory and shift settlement evidence, tax/discount/rounding reversal snapshots, audit history, and scoped configured sales-return printing.

Hotfix16 behavior and migration 000110 remain prerequisites. Hotfix17 adds migration 000111. The single authorized consolidated database-free verification gate passed. The clean release commit and immutable operator package are being finalized. Production access or mutation, database access, deployment, backup execution, migration execution, active-symlink changes, service actions, browser testing, tags, pushes, and remotes remain prohibited.
