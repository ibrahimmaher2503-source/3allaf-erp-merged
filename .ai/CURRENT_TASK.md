# Current Task — Hotfix50 Permission Labels and Section Selection

**Date:** 2026-09-29

**Change class:** I4

**Source baseline:** exact deployed Hotfix49 application commit `5c25390599f8d9e431599f0695881123e0f27349` at `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix49-5c25390599f8`.

**Branch:** `hotfix/v0.1.22-hotfix50-permission-labels`

**Worktree:** `/home/rajeh/worktrees/rajeh-erp-hotfix50`

**Status:** Complete and deployed. Production serves Hotfix50 application commit `0ff36dd24542d8378f6e9c24784e992b1d56ad74` (tree `90d224119ad75269627fce4b2c4cb96a8d02dfb2`) from `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix50-0ff36dd24542`.

Hotfix50 replaces raw permission codes and fragmentary action names with a shared Arabic/English business presentation for all 412 current permissions and 34 sections. Arabic and ar-EG never use English/key fallback. Each section has a localized `تحديد الكل` / `All` control with unchecked, mixed, and checked states; it changes only the section's complete active assignable set, preserves protected permissions, and remains pending until the existing Save action.

The focused browser check passed against isolated MariaDB `toyjoy_hf50_verify_2b798b36b23f`: 412 labels/34 sections in ar, ar-EG, and en; section-only selection; partial state; Save/reload; RTL/Cairo; and LTR. The isolated account is locked and evidence is retained at `/var/lib/toyjoy/hotfix50-validation/2b798b36b23f`. No production grants changed. No migration, dependency change, or asset build occurred.

Deployment completed through the established writer-quiescence, encrypted-backup, isolated restore/reconciliation, cache, atomic-activation, runtime-restoration, and recovery process. The deployment/recovery journal is `/var/lib/toyjoy/hotfix50-deployment/0ff36dd24542-20260929.v4d2J5`; its fresh encrypted archive SHA-256 is `113368a0d3c77c3dab45058a9fe72dd21643b0e711a40a0196b24466858da112`. The corrected read-only live check passed Arabic and English HTTP 200 responses, 34 localized sections, visible-label/raw-key exclusion, version, and health. No production permission assignment was changed for verification.
