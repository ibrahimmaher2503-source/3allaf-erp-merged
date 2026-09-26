# Current Task — Hotfix47 Authenticated Setup Redirect Repair

**Date:** 2026-09-26

**Change class:** I4

**Source baseline:** deployed Hotfix46 application commit `345c3af3f34a9e0ecd75e6694f3183f8f345cb99`

**Branch:** `hotfix/v0.1.22-hotfix46-user-management`

**Worktree:** `/home/rajeh/worktrees/rajeh-erp-hotfix46`

**Status:** Completed and deployed. Production serves Hotfix47 application commit `3ed4a9529c45806175424f70e8ce05993e8233cb` at `https://rajeh.xyz`.

Hotfix47 gives authenticated users one shared landing decision. An active global System Administrator who is authorized to manage users but has no visible active store is directed to the existing protected Users, Roles & Scopes setup step before dashboard readiness resolves company context. Dashboard and initial-setup entry points use the same decision, so incomplete setup reaches an accessible destination instead of redirecting through login. Tenant visibility, operational scopes, `is_super_admin`, verification, and all route middleware remain unchanged.

The focused live authenticated check confirmed user ID 2 / `karim` reaches the rendered Users, Roles & Scopes page from login, dashboard, authenticated-login, verification, and initial-setup entry paths. User ID 1 / `hassan` continues to reach the rendered dashboard and initial-setup pages normally. No account, role, permission, scope, business record, schema, migration, dependency, asset, or verification state changed.
