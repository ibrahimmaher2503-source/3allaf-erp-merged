# Current Milestone

**Date:** 2026-09-26

**Status:** Hotfix47 application commit `3ed4a9529c45806175424f70e8ce05993e8233cb` is deployed at `https://rajeh.xyz` from exact Hotfix46.

Post-login navigation now uses one shared landing policy. A global System Administrator who has no operational location scope is sent to the existing authenticated, verified and permission-gated Users, Roles & Scopes setup step. Dashboard and initial-setup entry points converge on that destination before company context resolution; users with visible stores retain their normal landing behavior. The fix grants no store visibility, branch/store scope, `is_super_admin` bypass or verification exception.

A fresh encrypted backup, isolated full restore and reconciliation, immutable-payload verification, atomic activation, runtime restoration and focused live authenticated navigation checks passed. No migration or production business-data write occurred. Deployment evidence: `/var/lib/toyjoy/hotfix47-deployment/3ed4a9529c45-20260926.ek14It`; isolated restore database: `toyjoy_hf47_restore_d3f54ec2406121368e` (locked, with no retained generated credentials).
