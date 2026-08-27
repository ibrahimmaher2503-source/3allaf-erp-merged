# Current Task — M50B.5 Parties, Bookings & Customers

**Date:** 2026-08-27
**Branch:** `feature/m50b5-parties-bookings`
**Baseline:** published `v0.1.9` commit `b134401d8860c9fb4b350928e021425faeb6f467`

## Status

- Complete in development: permission-scoped Party overview, calendar/agenda, booking filters, invoice and operating-order mobile records, centralized navigation, and unified customer Party history.
- Dashboard values come only from visible Party bookings, invoices, approved payments, invoice service lines, and operating orders. No package, venue, deposit, payable, or approval data is inferred beyond those authoritative records.
- Existing Party actions remain the mutation and concurrency boundary; routes, permissions, status transitions, calculations, wallets, and schema are unchanged.

## Verification boundary

- One locked Vite build, focused PHP/Blade syntax, route/navigation mapping, manifest/reference verification, and `git diff --check` were run.
- No database, automated suite, browser session, production, deployment, migration, tag, push, or remote operation was used.
