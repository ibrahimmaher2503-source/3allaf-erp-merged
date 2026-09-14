# Current Task — v0.1.22-hotfix37 Navigation, Help, and Branch UX

**Date:** 2026-09-14
**Change class:** Customer Notes Batch 1 hotfix release
**Source baseline:** exact active Hotfix36 commit `c5f6d57150a04d95915a72f5146a2aebd369bc83`
**Branch:** `hotfix/v0.1.22-hotfix37-navigation-help-branches`
**Status:** Implementation and the concise final verification are complete; the release commit and immutable offline packaging close the task.

Hotfix37 adds reusable accessible contextual help, repairs sidebar highlighting/scroll/state/keyboard behavior and destination ordering, removes branch-screen timezone and warehouse/POS shortcuts, restores company timezone to General Settings, adds a persisted per-user list-filter display preference through the existing UI-preference JSON, and removes only the user/role total cards. Routes, authorization, branch/store relationships, records, and business calculations remain unchanged.

No migration, database operation, automated suite, browser-control run, production access, deployment, activation, push, tag, or package installation is authorized. One Vite build and one concise database-free release verification are authorized. Activation must accept only exact Hotfix36, require migration 000111 already `Ran`, run no migration, perform only the optimized pre-switch checks named by the owner, and restore exact Hotfix36 after any post-switch failure.
