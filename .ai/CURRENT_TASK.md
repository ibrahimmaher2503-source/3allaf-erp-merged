# Current Task — v0.1.22-hotfix19 POS Livewire root and release-cache repair

**Date:** 2026-09-07
**Change class:** I4
**Source baseline:** exact Hotfix18 commit `c0fa3ed3623f7c259ba2c29109c08184bfa42eb4`
**Branch:** `hotfix/v0.1.22-hotfix19-pos-livewire-root-cache`
**Production baseline:** owner-confirmed and not re-checked: exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`; migration 000111 already Ran and must remain Ran.
**Status:** Hotfix19 implementation, package, and operator scripts are prepared, but final verification is blocked because this non-root session cannot execute the required host-user `rajeh` cache-generation proof without prohibited sudo or root authority.

Hotfix19 retains every Hotfix17/18 POS redesign and refund behavior. The confirmed `/pos` missing-root failure came from the refund wizard rendering no HTML when its conditional dialog was closed. The component now has one permanent wrapper, with the complete modal and every step/empty/loading/result state inside that wrapper. The cart, checkout panel, and product browser retain their existing permanent single roots.

The promotion and activation scripts create the extracted target bootstrap cache directory with `rajeh:rajeh` ownership and mode `0775`, recursively restore that ownership before Artisan, run every Artisan command through `runuser` as `rajeh`, and prove runtime writability immediately before and after cache generation. Exact active Hotfix16 remains the prerequisite and rollback target. Migration 000111 must already be Ran; Hotfix19 adds and executes no migration.

The owner authorized one focused final pass only: real Laravel Blade compilation, compiled PHP syntax, static root-contract checks for every changed POS Livewire template, the available database-free closed refund-wizard Livewire root/render contract, release-shaped archive extraction and `rajeh` cache-write/cache-generation proof, Bash syntax and safety checks for both scripts, and Git diff hygiene. Broad tests, browser loops, Composer, Vite, databases, production access, deployment, migration, tags, pushes, sudo, and remotes remain prohibited.
