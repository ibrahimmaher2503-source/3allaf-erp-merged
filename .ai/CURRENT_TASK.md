# Current Task — v0.1.22-hotfix10 UI Deduplication

**Date:** 2026-09-04
**Baseline:** exact final Hotfix9 source commit `b19bdf37d4a4fa05bf381e5eaf12ccfbf09c9f07`
**Branch:** `hotfix/v0.1.22-hotfix10-ui-dedup`
**Status:** The two UI-only duplicate presentations are removed in an isolated worktree. PHP syntax, database-fail-closed Blade compilation, locale JSON, source contracts, diff hygiene, and complete Vite/font asset checks pass. PHPUnit and browser automation were blocked by the execution policy before launch and are not claimed.

Hotfix10 removes only the upper standalone Roles & Permissions sidebar item and the lower page-level Product Catalog readiness summary. The canonical Basic Data → Essentials → Users, roles, and scopes entry and the upper setup/readiness header remain unchanged, including exact active-state routing.

Routes, controllers, authorization, permissions, readiness calculations, queries, catalog behavior, Hotfix8 performance changes, and Hotfix9 view-cache/navigation corrections are unchanged. No database, migration, production, deployment, service, symlink, tag, push, or remote operation is authorized or performed.
