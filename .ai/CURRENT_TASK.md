# Current Task — M50B.4 Pricing, Promotions & Discounts

**Date:** 2026-08-27
**Branch:** `feature/m50b4-pricing-promotions`
**Baseline:** published `v0.1.8` commit `faabe2ffcd0bd51b80aa004f57ffd5a9324f74da`

## Status

- Implemented in development: permission-aware Pricing overview, effective/scheduled pricing indicators, approval and unpriced-product exceptions, bounded recent activity, responsive price-version controls, and scoped label readiness.
- The authoritative model supports immutable price versions, effective periods, open-price bounds, POS cart discounts, and approval. It does not contain standalone promotion/campaign rules; the UI states that capability boundary instead of fabricating records.
- Added a permission-filtered session work context so the shell accurately distinguishes one store from all authorized stores. Supported Sales, Purchasing, Inventory, and Pricing views consume it; POS retains its authoritative shift/store transaction context.

## Verification boundary

- One locked Vite build, focused PHP/Blade/route/navigation checks, manifest/reference verification, and `git diff --check` passed.
- Automated tests and browser-control are not authorized for this task. No schema, migration, production, database, deployment, tag, push, or remote operation is allowed.
