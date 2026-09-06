# Current Milestone

**Date:** 2026-09-06
**Status:** v0.1.22-hotfix16 is the D3/I4 POS checkout and multi-customer release candidate from exact Hotfix15 commit `68699be35880dc154519c4e293c1a32bb69ef967`.

The milestone provides durable scoped simultaneous POS orders; per-order customer, cart, approval, tax, note, payment-draft, and total state; bounded customer/product lookup; cash, electronic, multi-card, and mixed settlement; exact Hotfix14 cash rounding; atomic idempotent sale posting through the existing domain services; an Arabic-first responsive checkout and success workflow; and company/outlet/document-safe receipt preview routing.

Hotfix15 behavior and migration 000109 remain prerequisites. Hotfix16 adds migration 000110. The consolidated database-free verification gate passed with only failed checks rerun after correction. A clean commit and immutable operator package remain. Production access, deployment, production backup, migration execution, active-symlink change, service operation, tag, push, and remote contact remain prohibited.
