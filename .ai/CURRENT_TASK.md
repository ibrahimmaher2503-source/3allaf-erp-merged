# Current Task — v0.1.22-hotfix34 Egyptian locale route-cache repair

**Date:** 2026-09-12
**Change class:** I4
**Source baseline:** exact Hotfix32 commit `2636d8dc1d345fa050e4e20c729a4cab9ac4a35a`
**Failed candidate:** Hotfix33 commit `3934420bf7dae9c50ffd2377b7128eab7a5ba442`
**Branch:** `hotfix/v0.1.22-hotfix34-egyptian-locale-route-cache`
**Status:** Immutable replacement release preparation is in progress in a separate `rajeh_ahmed` worktree; Hotfix33 release files remain untouched.

Hotfix34 carries forward the accepted Hotfix33 localization while replacing the locale-switch route closure with a strict-allowlist invokable controller. This removes locale switching from serialized-closure restoration and lets cached routes boot before the verifier exercises the real CSRF-protected endpoint. `ar`, `en`, all business behavior, database queries, and every Hotfix24–Hotfix32 repair remain unchanged.

One focused packaged-candidate verification is authorized. No automated suite, migration, database mutation, production access, deployment, activation, sudo/root execution, push, tag, browser/package installation, or unrelated functional change is authorized. Operator scripts must accept only exact Hotfix32, require migration 000111 already `Ran`, execute no migration, refuse pre-switch failures, and restore exact Hotfix32 after any post-switch failure.
