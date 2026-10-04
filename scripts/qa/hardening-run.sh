#!/usr/bin/env bash
set -Eeuo pipefail
# A separate disposable fixture avoids mixing the proven Phase 1 ledger with races.
# Resume an interrupted Phase 2 fixture directly with mariadb-run.sh hardening.
export QA_REUSE_RUNTIME=0 QA_PRESERVE_RUNTIME=0
exec "$(dirname "$0")/mariadb-run.sh" hardening
