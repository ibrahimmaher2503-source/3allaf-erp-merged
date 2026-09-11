# Current Milestone

**Date:** 2026-09-11
**Status:** v0.1.22-hotfix32 is the bounded I4 verification and activation repair from exact Hotfix31 commit `3ca22dbe6671f94aaec4132b84fb6a2c126cec82`.

The release corrects a stale supplier-only verification assertion and makes pre/post activation verification strictly fail closed with exact Hotfix31 rollback. The approved Hotfix31 purchase-invoice and price-list UI and every Hotfix24–Hotfix31 business, authorization, accounting, receiving, pricing, quick-create, scanner, keyboard, and purchase-order contract remain unchanged.

Focused verifier, forced-failure activation, PHP, Bash, asset-identity, and diff checks are required once. No migration is added or executed; migration 000111 remains an activation precondition. Promotion and activation are external root-operator steps and are not executed locally.
