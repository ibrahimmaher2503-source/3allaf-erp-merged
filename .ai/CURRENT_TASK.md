# Current Task — Hotfix50 Permission Labels and Section Selection

**Date:** 2026-09-29

**Change class:** I4

**Source baseline:** exact deployed Hotfix49 application commit `5c25390599f8d9e431599f0695881123e0f27349` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix49-5c25390599f8`.

**Branch:** `hotfix/v0.1.22-hotfix50-permission-labels`

**Worktree:** `/home/rajeh/worktrees/rajeh-erp-hotfix50`

**Status:** Implementation and the owner-authorized focused isolated verification are complete; candidate commit and production deployment are pending.

Hotfix50 replaces raw permission codes and fragmentary action names with a shared Arabic/English business presentation for all 412 current permissions and 34 sections. Arabic and ar-EG never use English/key fallback. Each section has a localized `تحديد الكل` / `All` control with unchecked, mixed, and checked states; it changes only the section's complete active assignable set, preserves protected permissions, and remains pending until the existing Save action.

The focused browser check passed against isolated MariaDB `toyjoy_hf50_verify_2b798b36b23f`: 412 labels/34 sections in ar, ar-EG, and en; section-only selection; partial state; Save/reload; RTL/Cairo; and LTR. The isolated account is locked and evidence is retained at `/var/lib/toyjoy/hotfix50-validation/2b798b36b23f`. No production grants changed. No migration, dependency change, or asset build is required. Deployment is explicitly authorized and must use the established encrypted backup, isolated restore/reconciliation, atomic activation, and recovery process.
