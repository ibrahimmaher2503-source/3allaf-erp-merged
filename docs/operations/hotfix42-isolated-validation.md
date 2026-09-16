# Hotfix42 Isolated Validation and Backup Evidence

This procedure is prepared only. It is never a production command sequence and it must not use production cache, queue, session, storage, or database credentials. The executable isolated runner, migration-repository helper, Hotfix41 compatibility probe, receipt verifier, promotion script, and activation script are delivered together in the separately authenticated Hotfix42 operator-tools bundle; they are not application payload.

## Operator-supplied prerequisites

The operator supplies only: an isolated MariaDB administrator defaults file, local MariaDB Unix-socket path, authenticated Hotfix41 database/private-storage/application artifacts, a validation `.env` template containing no endpoint credentials, the prepared validation parent, and the actual discovered application queue/Horizon service name. The runner creates a new database, DB-restricted user, storage/cache/session paths, and marked disposable UAT fixtures itself. Each validation process runs in its own network namespace with only loopback enabled and uses the MariaDB Unix socket; this blocks egress without affecting other `rajeh` processes. The receipt is not created until the database and private storage have both restored successfully into distinct, new isolated targets.

## Receipt evidence

The root-only receipt at `/home/rajeh/shared/toyjoy/deployment/hotfix42-backup-restore-verified.txt` must bind exact Hotfix41 and Hotfix42 identities, both archive/manifest hashes, absolute database/private-storage backup paths and SHA-256 values, an actual Laravel migration-repository state capture/hash, UTC backup/status/restore timestamps, a bounded validity window, the isolated restore target, and an absolute restore-evidence path/hash. The evidence file repeats every path/hash and records actual `database_restore=passed` and `private_storage_restore=passed` results. The verifier rejects incomplete, symlinked, stale, chronologically invalid, or mismatched evidence. It never creates a receipt.

## Isolated-only checks

After authenticating and extracting the isolated artifacts, the runner removes packaged caches before bootstrap, blocks egress, uses only isolated file cache/session/log storage, creates its DB-restricted user and marked UAT fixtures, then runs only the outstanding schema/backfill, multiple NULL-code draft/completion/immutable-code, PO partial-conversion, transactional authenticated three-locale, and exact-Hotfix41 compatibility checks. Product `name_en` is optional; supplier `name_en` remains required by the existing supplier schema and form contract. No fixture IDs are supplied by an operator.
