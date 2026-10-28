# Rajeh v2.0 — Non-secret Core Baseline Metadata

| Field | Verified value |
|---|---|
| Formal version | Rajeh v2.0 — Core Operations Baseline |
| Semantic version | v2.0.0 |
| Git tag | `v2.0.0-core-operations` (annotated, permanent) |
| Application baseline SHA | `55d4d5525ced50812f65941bb048dc81c6c7f641` |
| `origin/main` at freeze precheck | `f7e2a3c6122d26544a613ed63efd3e68aa877c1b` (later documentation commits are separate) |
| Hostname | `s4.instaparty.online` |
| Production domain | `https://rajeh.xyz` |
| Active and protected release | `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.56-cash-movement-55d4d55` |
| Release root | `/home/rajeh/releases/toyjoy/releases` |
| Public application symlink | `/home/rajeh/laravel` → protected release |
| Public business-storage symlink | release `public/storage` → `/home/rajeh/shared/toyjoy/storage/app/public` |
| Shared storage symlink | release `storage` → `/home/rajeh/shared/toyjoy/storage` |
| Shared environment location | release `.env` → `/home/rajeh/shared/toyjoy/.env` (path only; secret contents are excluded) |
| Database name | `rajeh_toyjoy` (MariaDB/MySQL) |
| Database baseline backup | `/home/rajeh/shared/toyjoy/backups/rajeh_toyjoy_20261028T183159Z_v2.0.0_core_baseline.sql.gz` |
| Database backup size and SHA-256 | 90,064 bytes; `04d03d5416280779ea25876f6566908cd7b6e8c4fa0cac222a9acf004f3aa564` |
| Database validation | gzip and readable SQL PASS; 172 tables including required Core tables; isolated socket-only restore PASS, 172 restored tables and Core queries |
| Storage baseline archive | `/home/rajeh/shared/toyjoy/backups/rajeh_storage_v2.0.0_core_baseline_20261028T183351Z.tar.gz` |
| Storage archive size and SHA-256 | 425 bytes; `663450deab370a4a7438c5ca4ece998e663ffa2849bbfeae3f826725b12394df` |
| Storage sources included | `/home/rajeh/shared/toyjoy/storage/app/private/attachments`, `/home/rajeh/shared/toyjoy/storage/app/private/exports`, `/home/rajeh/shared/toyjoy/storage/app/public` |
| Storage exclusions | Historical backup ZIPs, handoff snapshots, Livewire temporary uploads, application caches, sessions, logs, vendor, node_modules, and compiled release assets |
| Storage archive validation | gzip and tar listing PASS; active attachment/export directories contained no business files at freeze time |
| Pending migrations | 0; `migrate:status` read-only precheck succeeded |
| PHP CLI version | 8.5.12 |
| Laravel version | 13.24.0 |
| Node version | 20.20.2 |
| PHP-FPM service | `ea-php85-php-fpm.service` (active) |
| Production protection marker | `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.56-cash-movement-55d4d55/DO_NOT_DELETE_RAJEH_V2_CORE_BASELINE` |
| Backup protection marker | `/home/rajeh/shared/toyjoy/backups/DO_NOT_DELETE_RAJEH_V2_CORE_BASELINE` |
| Freeze date | 2026-10-28 UTC; database snapshot `18:31:59Z`, storage snapshot `18:33:51Z` |

The formal v2.0.0 milestone supersedes the older `v1.22` footer label. The immutable running release remains unchanged; the canonical PHP footer version can be updated in a later reviewed runtime release. The active application and tag point to the same application SHA, while current main may advance through documentation-only commits.

This document contains no passwords, API keys, tokens, secret environment values, or database credentials. The named snapshots and release are protected recovery assets. Full historical data restoration requires explicit owner authorization; see [recovery](CORE_BASELINE_RECOVERY.md).
