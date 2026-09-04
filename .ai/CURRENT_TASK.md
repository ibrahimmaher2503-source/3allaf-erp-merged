# Current Task — v0.1.22-hotfix11 Company-owner RBAC Release

**Date:** 2026-09-04
**Source baseline:** exact approved D3 commit `e53decafea915820e09d251d247b6b1f37787b24` (tree `3a5b6722c7cd6be63480d68268a143964c22cbb6`)
**Branch:** `feature/company-owner-rbac-approval-navigation`
**Status:** The approved D3 company-owner RBAC and Approval Inbox navigation change is complete. Release finalization changes only the application version and its two direct source assertions to `0.1.22-hotfix11`; the passed 4-test/49-assertion focused authorization suite is reused.

Hotfix11 provides the protected non-super-admin `company-owner` profile with all active company-scoped permissions while excluding platform, infrastructure, audit, impersonation, cross-company, and super-admin bypass capabilities. Administration Center and Activity Log remain super-admin-only in navigation and direct access. Approval Inbox appears exactly once under Home, immediately after Notifications & Tasks, using the existing approval-capability set.

The root-only interactive `company-owner:provision-karim` command remains unchanged and unexecuted. It requires owner-supplied company and identity values plus a hidden temporary password after activation and a fresh backup. No Composer install, Vite rebuild, database, production, deployment, service, HTTP, tag, push, or remote/global Git action occurred during source finalization.
