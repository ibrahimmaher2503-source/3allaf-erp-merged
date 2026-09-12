# Current Task — v0.1.22-hotfix33 Egyptian Arabic integration

**Date:** 2026-09-12
**Change class:** I4
**Source baseline:** exact Hotfix32 commit `2636d8dc1d345fa050e4e20c729a4cab9ac4a35a`
**Localization source:** commit `be300bfb039316a3f89b29f2cd2c29bd73bd0add` from `ui/egyptian-arabic-locale`
**Branch:** `hotfix/v0.1.22-hotfix33-egyptian-arabic`
**Status:** Integration and bounded release preparation are in progress in a separate `rajeh_ahmed` worktree.

Hotfix33 integrates the completed separate `ar-EG` locale while preserving `ar`, `en`, and every Hotfix24–Hotfix32 functional repair. Only locale registration, validation/switching, session/cookie preference persistence, fallback resolution, Arabic-family presentation, RTL, and Cairo behavior are in scope. The localization source's POS customer-search `ORDER BY` change was rejected so exact Hotfix32 query behavior remains.

One focused integration verification is authorized. No automated suite, migration, database mutation, production access, deployment, activation, sudo/root execution, push, tag, browser/package installation, or unrelated functional change is authorized. Operator scripts must accept only exact Hotfix32, require migration 000111 already `Ran`, execute no migration, and restore exact Hotfix32 after any post-switch failure.
