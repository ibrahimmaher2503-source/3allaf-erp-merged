# Current Task — v0.1.22-hotfix18 POS Blade and runtime-preparation repair

**Date:** 2026-09-07
**Change class:** D3 / I4
**Source baseline:** exact Hotfix17 commit `6f47db7a62b38e8bac2aa9a4983a63611a8c17e4`
**Branch:** `hotfix/v0.1.22-hotfix18-pos-blade-repair`
**Production baseline:** owner-verified and not re-checked: exact Hotfix16 commit `31883f5c823458d30738708ec96e1d02337f4c9b`, migration 000111 already Ran, maintenance OFF.
**Status:** The confirmed POS Blade structural defect and release runtime-preparation gap are corrected; one focused verification, release commit, and immutable operator package are being finalized.

Hotfix18 retains every Hotfix17 compact POS and refund change. It separates the nested refund authorization directives in the POS page and checkout panel, replaces the cart’s invalid multi-statement inline PHP directive with valid single-expression directives, and parenthesizes one confirmed PHP 8.5 purchasing-return print fallback. The real Livewire-aware compiler now produces valid PHP for every application Blade template.

The operator scripts create `bootstrap/cache` with `rajeh:rajeh` ownership and mode `0770` before any Artisan command, and every Artisan invocation uses `runuser` as `rajeh`. Activation requires exact active Hotfix16, accepts migration 000111 as already Ran or Pending, safely runs the idempotent migration command, and retains exact Hotfix16 as the rollback target. No migration is added.

The owner authorized one focused database-free pass: all Blade templates through the real Laravel compiler, compiled POS PHP syntax, the existing focused route/render contract when supported without a database, changed PHP syntax, locale JSON, diff hygiene, and operator-script Bash syntax. Broad tests, browser loops, databases, production, deployment, backup execution, migration execution, Composer, tags, pushes, and remote contact remain prohibited. Vite is skipped because no frontend input changes.
