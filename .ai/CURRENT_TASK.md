# Current Task — M53.1 Guided Setup Navigation Hotfix

**Date:** 2026-08-28
**Branch:** `feature/v0.1.15-visual-ux-corrections`
**Baseline:** published `v0.1.14` commit `33d80f9f56265cda070025cc37dfbf2608d81a31`

## Status

- Complete in development after genuine guarded Arabic/English rendering with the existing built assets.
- The primary Setup context now exposes permission-filtered Previous/Next controls derived directly from `InitialSetupStatus`; nested Setup pages resolve to their owning step and retain `setup=true` plus the authoritative `setup_step` identifier.
- The authoritative Setup journey exposes 22 unique permission-filtered steps: 5 Basics, 5 Settings, and 12 Operational Readiness.
- Persistent localized Setup context, reliable action-scoped loading controls, semantic table actions, logical table spacing, and active sidebar location are visibly verified.
- Routes, methods, permissions, policies, validation, schema, calculations, and workflows remain unchanged.

## Verification boundary

- One final locked Vite build produced manifest SHA-256 `dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0`.
- Genuine loopback GET/HEAD rendering used a read-only database session with request rollback and task-local sessions, logs, caches, and compiled views. No mutation form was submitted.
- The original 20-page evidence remains accepted. The seven-case M53.1 evidence and 22-destination verification record are stored outside Git under `v015-setup-navigation-hotfix-20260828`.
- No frontend build was required for M53.1; the accepted manifest remains `dabbde0ffadfb86f62400eb67668010949dd1b7eeb931174c7c72a0958e988b0`.
- No production mutation, schema change, deployment, migration, version bump, tag, push, or remote operation occurred.
