# Active Milestone — M50B.5 Parties, Bookings & Customers

**Date:** 2026-08-27
**Current phase:** Item 50 isolated UI/UX release phase
**Current milestone:** M50B.5
**Status:** Complete in development after the single locked build and focused syntax, route, permission mapping, manifest, and diff verification.

M50B.5 reuses the published v0.1.9 shell and M50B page foundation. The existing `parties.bookings.index` route is the permission-aware module landing page, while the existing calendar, invoice, payment, operating-order, booking detail, and customer-profile routes retain their established authorization and action boundaries.

The authoritative Party model has free-form booking locations and invoice service lines, but no package or venue master tables. The workspace presents those real fields without inventing package inventory, venue availability, deposits, or separate customer identities.
