# Current Milestone

**Date:** 2026-09-04
**Status:** v0.1.22-hotfix11 finalizes the approved D3 company-owner RBAC and Approval Inbox navigation change from exact commit `e53decafea915820e09d251d247b6b1f37787b24`. Only the release version and direct version assertions change during finalization; the D3 functional implementation and its passed 4-test/49-assertion verification remain unchanged.

The release preserves Hotfix10 as the exact production rollback baseline, reuses its authenticated vendor and compiled frontend assets without Composer or Vite, and prepares separate immutable promotion, activation, and pre-Karim backup workflows. Production remains untouched until an authorized root operator executes those workflows in order.
