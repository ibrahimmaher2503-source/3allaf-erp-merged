# Current Task — v0.1.22-hotfix35 Egyptian localization completion

**Date:** 2026-09-12
**Change class:** I4
**Source baseline:** exact active Hotfix34 commit `397fd3529a087f08f402fd11c88692481bb0654b`
**Localization source:** `57cb2a85666cc7d9cfad02edce33eb5bfa766df6`
**Branch:** `hotfix/v0.1.22-hotfix35-egyptian-localization-completion`
**Status:** Hotfix35 integrates the completed Egyptian Arabic presentation layer onto exact Hotfix34; immutable offline release preparation and unexecuted operator scripts are in progress.

Hotfix35 preserves Hotfix34's controller-based locale route and route-cache repair while completing `ar-EG` copy and presentation-only source labels. `ar`, `en`, business behavior, database queries, permissions, routes, search, keyboard workflows, accounting behavior, and internal PHP class names remain unchanged.

One concise packaged-candidate verification is authorized. No automated suite, migration, database mutation, production access, deployment, activation, sudo/root execution, push, tag, browser/package installation, or unrelated functional change is authorized. Operator scripts must accept only exact Hotfix34, require migration 000111 already `Ran`, execute no migration, refuse pre-switch failures, and restore exact Hotfix34 after any post-switch failure.
