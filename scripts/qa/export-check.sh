#!/usr/bin/env bash
set -Eeuo pipefail
exec "${QA_PHP:-php}" "${QA_ROOT:-$(git rev-parse --show-toplevel)}/scripts/qa/export-check.php"
