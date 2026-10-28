# Rajeh v2.0 — Core Baseline Recovery

The protected code baseline is annotated tag `v2.0.0-core-operations` at application SHA `55d4d5525ced50812f65941bb048dc81c6c7f641`, packaged as `/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.56-cash-movement-55d4d55`. Its `DO_NOT_DELETE_RAJEH_V2_CORE_BASELINE` marker and the backup-directory marker must survive ordinary cleanup. See [metadata](CORE_BASELINE_METADATA.md) for exact paths and SHA-256 values.

## Choose the recovery type

If the request says only “rollback code” or “go back to v2”, confirm which action the owner intends before any data restoration:

- **A. Code-only recovery:** select the protected immutable application release while preserving the current database and persistent storage.
- **B. Full historical recovery:** restore the v2 application, v2 database snapshot, and v2 storage snapshot. This requires explicit owner authorization.

**FULL DATA RECOVERY IS DESTRUCTIVE.** Restoring the v2.0 database snapshot can erase legitimate transactions and data created after 2026-10-28. A code-only rollback does not automatically restore the database. Never infer authorization for full data recovery from a code rollback request.

## Code-only recovery

1. Record the active release, current application SHA, Production health, schema/migration state, and a rollback target. Confirm the protected release and marker exist and its `.release-source-sha` equals the tag target. Confirm the current database schema remains compatible with the older v2 code; stop for an owner decision if it is not.
2. Follow the last proven Production deployment controls: fresh backup if a Production data or schema change is involved, maintenance and writer drain as required, and a recoverable journal. Preserve shared `.env`, storage, and `public/storage` links. Do not run migrations or seeders for a code-only recovery.
3. Atomically replace `/home/rajeh/laravel` with a symlink to the protected immutable release. Immediately reload `ea-php85-php-fpm.service`.
4. Prepare/clear only runtime caches needed for this exact final release path as the `rajeh` runtime user. Do not edit the protected application source or rebuild assets in Production. Return maintenance OFF.
5. Verify the real host `https://rajeh.xyz/`, `/up`, and `/login` return 200; protected routes return the expected guest response; CSS/JS load; PHP-FPM is active; no new Laravel/PHP errors or HTTP 5xx appear. If validation fails, atomically restore the previously recorded release, reload PHP 8.5 FPM, restore maintenance OFF, and verify the public host.

This procedure changes application code selection only. Existing database and uploaded business files remain current. Later migrations may make older code incompatible; inspect before switching.

## Full v2.0 historical recovery — owner approval required

1. Obtain explicit authorization for **full application + database + storage** recovery, with acknowledgment of the destructive date boundary and affected later transactions/uploads. Identify the approved recovery timestamp and service interruption window.
2. Take fresh, verified backups of the **current** database and current persistent storage before replacing anything. Preserve them independently of the protected v2 snapshots. Verify SHA-256 and readability of all current and v2 assets.
3. Rehearse the v2 database and storage restore in an isolated disposable environment. Compare schema, table counts, attachment paths, and application compatibility. Prepare a rollback plan to the just-captured current state.
4. Quiesce writers and enter maintenance. Restore the v2 database snapshot to the explicitly approved target database and restore the v2 persistent-storage archive to the approved shared paths. Apply the protected v2 release atomically, immediately reload PHP 8.5 FPM, and restore only appropriate final-path caches. Never use `migrate:fresh`, `migrate:refresh`, `db:wipe`, generic reset, or `ProductionSeeder` as a substitute for the reviewed restore.
5. Reconcile database and storage references, verify the real host and protected operational flows, inspect logs, ensure maintenance OFF, and retain the current-state backup for reversal. Record exactly what later data was lost or recovered.

The baseline database snapshot and storage archive are protected reference points, not routine deployment artifacts. No full recovery was performed during the baseline freeze.
