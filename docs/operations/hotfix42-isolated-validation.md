# Hotfix42 Isolated Validation and Backup Evidence

This procedure is prepared only. It is never a production command sequence and it must not use production cache, queue, session, storage, or database credentials.

## Operator-supplied prerequisites

The operator supplies: a new, non-existent MariaDB database name beginning `toyjoy_hotfix42_validation_`; a new empty runtime/storage root; a protected validation `.env` with the isolated database, isolated file cache/session/log paths, `MAIL_MAILER=log`, `BROADCAST_CONNECTION=log`, and no worker; a Hotfix41 database and private-storage backup; and credentials for the isolated database only. The receipt is not created until the database and private storage have both restored successfully into distinct, new isolated targets.

## Receipt evidence

The root-only receipt at `/home/rajeh/shared/toyjoy/deployment/hotfix42-backup-restore-verified.txt` must identify Hotfix42 and the exact Hotfix41 pre-migration release/tree, absolute database/private-storage backup paths and SHA-256 values, UTC backup and restore timestamps, the isolated restore target, the pre-migration `migrate:status` SHA-256, and an absolute restore-evidence path and SHA-256. The evidence file must repeat those paths/hashes and record `database_restore=passed` and `private_storage_restore=passed`. The activation verifier recomputes file hashes and rejects incomplete, symlinked, or mismatched evidence.

## Isolated-only checks

After restoring the Hotfix41 baseline, run `migrate:status`, `schema-guard.php before`, only migration 000115 then 000116, `schema-guard.php after`, the focused Hotfix42 verifier, and the transactional authenticated three-locale smoke. Draft completion/immutable identity and partial-PO conversion require operator-supplied disposable fixture identifiers and values; do not point them at production records or substitute static checks. Product `name_en` is optional; supplier `name_en` remains required by the existing supplier schema and form contract.
