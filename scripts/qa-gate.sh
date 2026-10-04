#!/usr/bin/env bash
set -Eeuo pipefail
mode=${1:-}
[[ "$mode" == fast || "$mode" == release ]] || { echo "Usage: $0 {fast|release}" >&2; exit 2; }
source "$(dirname "$0")/qa/environment.sh"
root=$QA_ROOT
export QA_APPLICATION_TREE=$(git rev-parse HEAD:app HEAD:config HEAD:database HEAD:routes HEAD:resources HEAD:lang HEAD:bootstrap/app.php HEAD:composer.lock HEAD:package-lock.json | sha256sum | cut -d ' ' -f1)
if [[ -n ${QA_RESUME_REPORT:-} ]]; then
  git diff --quiet HEAD -- app config database routes resources lang bootstrap/app.php composer.lock package-lock.json || { echo 'FAIL runtime source changed; resume evidence cannot be reused';exit 1; }
  python3 - "$QA_RESUME_REPORT" "$QA_APPLICATION_TREE" <<'SOURCE'
import json,sys
x=json.load(open(sys.argv[1]))
if x.get('application_tree')!=sys.argv[2]: raise SystemExit('FAIL checkpoint belongs to different/unrecorded application source')
SOURCE
fi
[[ "$root" == "${RAJEH_QA_ROOT:-$root}" ]] || exit 1
run_dir="$root/.ai/qa/runs/$(date -u +%Y%m%dT%H%M%SZ)-$RANDOM-$mode"
mkdir -p "$run_dir"
export QA_RUN_DIR="$run_dir"
results="$run_dir/results.tsv"
printf 'name\tstatus\tseconds\tdetail\n' > "$results"
start_all=$(date +%s)
preserve_requested=${QA_PRESERVE_RUNTIME:-0}
export QA_PRESERVE_RUNTIME=1
cleanup_gate() {
  if [[ "$preserve_requested" == 0 && -f "$root/.qa-runtime/last-db" ]]; then
    mapfile -t db_runtime < "$root/.qa-runtime/last-db"
    if [[ ${db_runtime[0]} == /tmp/rajeh-qa-mariadb.* ]]; then rm -rf -- "${db_runtime[0]}";rm "$root/.qa-runtime/last-db";fi
  fi
}
trap cleanup_gate EXIT
run_check() {
  local name=$1;shift
  local cached
  cached=$(python3 - "$name" "${QA_RESUME_REPORT:-}" <<'CACHE'
import json,sys
if sys.argv[2]:
    report=json.load(open(sys.argv[2]))
    for x in report['checks']:
        if x['name']==sys.argv[1] and x['status'] in ('PASS','PASS WITH WARNINGS'):
            print('\t'.join([x['name'],x['status'],str(x['seconds']),'Preserved from '+sys.argv[2]+'; '+x['detail']]));break
CACHE
)
  if [[ -n "$cached" ]]; then printf '%s\n' "$cached" >> "$results";printf '%-48s PRESERVED PASS\n' "$name";return;fi
  local slug=${name//[^a-zA-Z0-9]/_} log start rc=0
  log="$run_dir/$slug.log";start=$(date +%s)
  "$@" >"$log" 2>&1 || rc=$?
  local status=PASS;[[ $rc -eq 0 ]] || status=FAIL;[[ $rc -eq 77 ]] && status='PASS WITH WARNINGS'
  local seconds=$(( $(date +%s)-start )) detail
  detail=$(tail -n 3 "$log" | tr '\n\t' '  ' | cut -c 1-600)
  printf '%s\t%s\t%d\t%s\n' "$name" "$status" "$seconds" "$detail" >> "$results"
  printf '%-48s %s (%ss)\n' "$name" "$status" "$seconds"
}
run_check 'tool self-check' "$root/scripts/qa/tools-self-check.sh" "$mode"
run_check 'CodeGraph current-root/index' bash -c 'set -eo pipefail; if [[ ${CI:-} == true ]]; then echo "CI excludes developer-only semantic indexing";exit 0;fi; test -f "$QA_ROOT/.codegraph/codegraph.db"; codegraph status "$QA_ROOT" | tee "$QA_RUN_DIR/codegraph-status.txt"; grep -Fq "Project: $QA_ROOT" "$QA_RUN_DIR/codegraph-status.txt"'
run_check 'PHP syntax changed/relevant files' "$root/scripts/qa/php-syntax.sh"
run_check 'Larastan/PHPStan controlled baseline' "$QA_PHP" "$root/vendor/bin/phpstan" analyse -c "$root/phpstan.qa.neon" --no-progress --memory-limit=1G
run_check 'focused critical regression suite' "$QA_PHP" "$root/vendor/bin/phpunit" -c "$root/phpunit.qa.xml" --testdox
run_check 'isolated MariaDB integrity scanner' "$root/scripts/qa/mariadb-run.sh" integrity
if [[ -f "$root/.qa-runtime/last-db" ]]; then export QA_REUSE_RUNTIME=1;fi
run_check 'queue/scheduler/runtime configuration'  "$root/scripts/qa/runtime-check.sh"
run_check 'compact browser smoke' "$root/scripts/qa/mariadb-run.sh" compact
if [[ "$mode" == release ]]; then
 run_check 'strong financial matrix and boundary suite' "$QA_PHP" "$root/vendor/bin/phpunit" -c "$root/scripts/qa/phpunit.xml" --filter 'FinancialMatrixTest|FinancialBoundaryTest|FinancialPrecisionClosureTest' --testdox
 run_check 'isolated financial concurrency hardening' "$root/scripts/qa/hardening-run.sh"
 run_check 'isolated accounting/inventory reconciliation' "$root/scripts/qa/mariadb-run.sh" reconcile
 run_check 'export/PDF/receipt system validation' "$root/scripts/qa/mariadb-run.sh" export
 run_check 'Semgrep Laravel security scan' semgrep --config "$root/.semgrep/laravel.yml" --error --json --output "$run_dir/semgrep.json" "$root/app" "$root/routes" "$root/config"
 run_check 'scoped critical mutation testing' "$root/scripts/qa/mutation-check.sh"
 run_check 'safe isolated backup/restore verification' "$root/scripts/qa/mariadb-run.sh" backup
 run_check 'expanded Admin/User/Cashier browser journeys' "$root/scripts/qa/mariadb-run.sh" browser
fi
export QA_GATE_DURATION=$(( $(date +%s)-start_all )) QA_GATE_MODE="$mode"
python3 "$root/scripts/qa/render-report.py" "$results" "$root/.ai/qa/latest-$mode.json" "$root/.ai/qa/latest-$mode.md"
cp "$root/.ai/qa/latest-$mode.json" "$run_dir/report.json"
cp "$root/.ai/qa/latest-$mode.md" "$run_dir/report.md"
printf 'Run directory: %s\nElapsed: %ss\n' "$run_dir" "$QA_GATE_DURATION"
! grep -q $'\tFAIL\t' "$results"
