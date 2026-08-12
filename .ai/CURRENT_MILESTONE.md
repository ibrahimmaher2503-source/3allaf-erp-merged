# Active Milestone — TSK-046 Production Setup UI Hardening

**Date:** 2026-08-12
**Current phase:** Phase 6 release/configuration follow-on
**Current milestone:** DM 6.4 Production configuration and cutover readiness
**Status:** Local implementation and isolated MariaDB verification completed. Production inputs, independent approvals, headed-browser UAT, backup/restore, and release gates remain open.

## Active boundaries

- The System Administrator can perform all guarded setup actions but cannot bypass maker/checker separation.
- Stages 1–8 require approved real operational data; Customer/Party data is optional and genuine-only.
- No synthetic business, inventory, financial, customer, or Party data enters Production through seeding.
- Opening inventory posts through immutable approved adjustments, never direct balance edits.
- Production readiness is not inferred from permissions or empty-screen availability.
