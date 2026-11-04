#!/usr/bin/env bash
set -Eeuo pipefail
mode=${1:?mode is required}
shift
source "$(dirname "$0")/environment.sh"
mkdir -p "$QA_ROOT/.qa-runtime"
if [[ ${QA_REUSE_RUNTIME:-0} == 1 ]]; then
  mapfile -t saved < "$QA_ROOT/.qa-runtime/last-db"
  work=${saved[0]}; saved_db=${saved[1]}
  [[ "$work" == /tmp/rajeh-qa-mariadb.* && -f "$work/schema-ready" && "$saved_db" == qa_rajh_* ]] || exit 1
else work=$(mktemp -d /tmp/rajeh-qa-mariadb.XXXXXX); fi
socket=$work/mariadb.sock
cleanup() {
  if [[ -n ${http_pid:-} ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi
  if [[ -n ${server_pid:-} ]]; then mariadb-admin --no-defaults --protocol=socket --socket="$socket" -uroot shutdown >/dev/null 2>&1 || true; wait "$server_pid" 2>/dev/null || true; fi
  if [[ ${QA_PRESERVE_RUNTIME:-0} == 1 ]]; then
    printf 'Preserved QA runtime: %s; DB %s\n' "$work" "$DB_DATABASE"
    printf '%s\n%s\n' "$work" "$DB_DATABASE" > "$QA_ROOT/.qa-runtime/last-db"
  else rm -rf -- "$work"; fi
}
trap cleanup EXIT
if [[ ${QA_REUSE_RUNTIME:-0} != 1 ]]; then mariadb-install-db --no-defaults --datadir="$work/data" --auth-root-authentication-method=normal --skip-test-db >"$work/install.log" 2>&1; fi
mariadbd --no-defaults --innodb-flush-log-at-trx-commit=0 --sync-binlog=0 --datadir="$work/data" --socket="$socket" --skip-networking --pid-file="$work/server.pid" --log-error="$work/server.log" >"$work/stdout.log" 2>&1 &
server_pid=$!
for _ in $(seq 1 60); do mariadb-admin --no-defaults --protocol=socket --socket="$socket" -uroot ping >/dev/null 2>&1 && break; sleep 0.2; done
mariadb-admin --no-defaults --protocol=socket --socket="$socket" -uroot ping >/dev/null 2>&1 || { cat "$work/server.log" >&2; exit 1; }
export DB_DATABASE="${saved_db:-qa_rajh_$(date -u +%Y%m%d%H%M%S)_$RANDOM}" DB_USERNAME=root DB_SOCKET="$socket"
printf 'Disposable database (before creation): %s; socket %s; networking disabled
' "$DB_DATABASE" "$socket"
[[ ${#DB_DATABASE} -lt 60 ]]
if [[ ${QA_REUSE_RUNTIME:-0} != 1 ]]; then mariadb --no-defaults --protocol=socket --socket="$socket" -uroot -e "CREATE DATABASE \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"; fi
printf '%s\n' "$DB_DATABASE" > "$work/database-name"
export QA_STORAGE="$work/storage"
mkdir -p "$QA_STORAGE/framework/"{cache/data,sessions,views} "$QA_STORAGE/logs" "$QA_STORAGE/app/private"
if [[ ! -f "$work/schema-ready" ]]; then
  "$QA_PHP" "$QA_ROOT/scripts/qa/console.php" migrate --force --no-interaction --quiet
  touch "$work/schema-ready"
fi
case "$mode" in
 focused)
   mkdir -p "$QA_STORAGE/$DB_DATABASE"
   APP_ENV=testing APP_LOCALE=en BCRYPT_ROUNDS=4 FILESYSTEM_DISK=local \
     QA_DISPOSABLE_RUNTIME="$work" QA_STORAGE_PATH="$QA_STORAGE/$DB_DATABASE" \
     "$QA_PHP" "$QA_ROOT/vendor/bin/phpunit" -c "$QA_ROOT/phpunit.qa.xml" --testdox "$@"
   ;;
 product-cost)
   if [[ ${QA_COST_VERIFY_ONLY:-0} == 1 ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/product-cost-regression.php" verify-browser; exit; fi
   if [[ ${QA_COST_BROWSER_ONLY:-0} != 1 ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/product-cost-regression.php"; fi
   export SESSION_DRIVER=file
   port=$("$QA_PHP" -r '$s=stream_socket_server("tcp://127.0.0.1:0");echo parse_url("tcp://".stream_socket_get_name($s,false),PHP_URL_PORT);')
   export QA_BROWSER_URL="http://127.0.0.1:$port" APP_URL="http://127.0.0.1:$port"
   "$QA_PHP" -S "127.0.0.1:$port" "$QA_ROOT/scripts/qa/browser-router.php" >"$work/http.log" 2>&1 &
   http_pid=$!
   for _ in $(seq 1 50); do curl -fsS "$QA_BROWSER_URL/up" >/dev/null 2>&1 && break; sleep 0.2; done
   node "$QA_ROOT/scripts/qa/product-cost-browser.mjs"
   ;;
 integrity) if [[ ${QA_INTEGRITY_SCAN_ONLY:-0} != 1 ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/catalog-regression.php"; fi; "$QA_PHP" "$QA_ROOT/scripts/qa/db-integrity.php" ;;
 hardening) "$QA_PHP" "$QA_ROOT/scripts/qa/financial-domain.php" ;;
 reconcile) "$QA_PHP" "$QA_ROOT/scripts/qa/reconciliation.php" ;;
	compact|browser|batch-browser|batch-e2e|v2-core-polish|pos-reference|pos-theme-r8|pos-completion|post-pos-repairs|purchase-approval|purchase-invoice-hotfix|commercial-ux|pos-smart-returns|pos-quantity-hotfix|cashier-gift-batch|inventory-menu-b|postdeploy-corrective|open-shift-quotation-batch|corrective-ux-batch|small-ux-batch|price-discount-browser)
    if [[ "$mode" == purchase-approval || "$mode" == purchase-invoice-hotfix ]] && [[ ${QA_PURCHASE_FIXTURE_READY:-0} != 1 ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/purchase-approval-fixture.php"; fi
    if [[ "$mode" == browser || "$mode" == batch-browser || "$mode" == batch-e2e || "$mode" == v2-core-polish || "$mode" == pos-reference || "$mode" == pos-theme-r8 || "$mode" == pos-completion || "$mode" == post-pos-repairs || "$mode" == commercial-ux || "$mode" == pos-smart-returns || "$mode" == pos-quantity-hotfix || "$mode" == cashier-gift-batch || "$mode" == inventory-menu-b || "$mode" == postdeploy-corrective || "$mode" == open-shift-quotation-batch || "$mode" == corrective-ux-batch || "$mode" == small-ux-batch ]]; then if [[ "$mode" != open-shift-quotation-batch || ${QA_REUSE_RUNTIME:-0} != 1 ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/browser-fixture.php" "$work/accounts.json"; fi; export QA_BROWSER_ACCOUNTS="$work/accounts.json"; fi
    if [[ "$mode" == batch-browser || "$mode" == batch-e2e || "$mode" == v2-core-polish || "$mode" == pos-reference || "$mode" == pos-theme-r8 || "$mode" == pos-completion || "$mode" == commercial-ux || "$mode" == pos-smart-returns || "$mode" == pos-quantity-hotfix || "$mode" == cashier-gift-batch || "$mode" == inventory-menu-b || "$mode" == postdeploy-corrective || "$mode" == open-shift-quotation-batch || "$mode" == corrective-ux-batch || "$mode" == small-ux-batch ]]; then if [[ "$mode" != open-shift-quotation-batch || ${QA_REUSE_RUNTIME:-0} != 1 ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/pos-reference-fixture.php"; fi; fi
    if [[ "$mode" == batch-e2e ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/v2-current-batch-e2e-fixture.php"; export QA_BATCH_E2E_FIXTURE="$QA_STORAGE/v2-current-batch-e2e.json"; fi
    if [[ "$mode" == corrective-ux-batch || "$mode" == small-ux-batch ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/open-shift-quotation-fixture.php"; fi
    if [[ "$mode" == open-shift-quotation-batch && ${QA_REUSE_RUNTIME:-0} != 1 ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/open-shift-quotation-fixture.php"; fi
    if [[ "$mode" == price-discount-browser ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/price-discount-browser-fixture.php"; export QA_PRICE_DISCOUNT_FIXTURE="$QA_STORAGE/price-discount-browser.json" QA_BROWSER_ACCOUNTS="$work/accounts.json"; fi
    if [[ "$mode" == cashier-gift-batch || "$mode" == inventory-menu-b || "$mode" == postdeploy-corrective ]]; then mkdir -p "$QA_STORAGE/$DB_DATABASE"; export QA_STORAGE_PATH="$QA_STORAGE/$DB_DATABASE"; "$QA_PHP" "$QA_ROOT/scripts/qa/cashier-gift-batch-fixture.php"; export QA_CASHIER_GIFT_FIXTURE="$QA_STORAGE_PATH/cashier-gift-batch.json"; fi
    if [[ "$mode" == pos-smart-returns ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/pos-smart-returns-fixture.php" "$work/pos-smart-returns.json"; export QA_POS_RETURN_FIXTURE="$work/pos-smart-returns.json"; fi
    if [[ "$mode" == pos-quantity-hotfix ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/pos-quantity-fixture.php"; fi
    if [[ "$mode" == commercial-ux ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/commercial-ux-fixture.php" "$work/commercial.json"; export QA_COMMERCIAL_FIXTURE="$work/commercial.json"; fi
    if [[ "$mode" == post-pos-repairs ]]; then "$QA_PHP" "$QA_ROOT/scripts/qa/post-pos-repairs-fixture.php" "$work/post-pos-repairs.json"; export QA_POST_POS_FIXTURE="$work/post-pos-repairs.json" QA_POST_POS_SCREENSHOTS="${QA_POST_POS_SCREENSHOTS:-$(mktemp -d /tmp/rajeh-r1-r7-review.XXXXXX)}"; echo "Review screenshots: $QA_POST_POS_SCREENSHOTS"; fi
   export SESSION_DRIVER=file
   port=$("$QA_PHP" -r '$s=stream_socket_server("tcp://127.0.0.1:0");echo parse_url("tcp://".stream_socket_get_name($s,false),PHP_URL_PORT);')
   export QA_BROWSER_URL="http://127.0.0.1:$port"
   export APP_URL="$QA_BROWSER_URL"
   "$QA_PHP" -S "127.0.0.1:$port" "$QA_ROOT/scripts/qa/browser-router.php" >"$work/http.log" 2>&1 &
   http_pid=$!
   for _ in $(seq 1 50); do curl -fsS "$QA_BROWSER_URL/up" >/dev/null 2>&1 && break; sleep 0.2; done
   if [[ "$mode" == postdeploy-corrective ]]; then
     node "$QA_ROOT/scripts/qa/postdeploy-corrective-browser.mjs"
   elif [[ "$mode" == price-discount-browser ]]; then
     node "$QA_ROOT/scripts/qa/price-discount-browser.mjs"
   elif [[ "$mode" == small-ux-batch ]]; then
     node "$QA_ROOT/scripts/qa/small-ux-browser.mjs"
   elif [[ "$mode" == corrective-ux-batch ]]; then
     node "$QA_ROOT/scripts/qa/corrective-ux-browser.mjs"
   elif [[ "$mode" == open-shift-quotation-batch ]]; then
     node "$QA_ROOT/scripts/qa/open-shift-quotation-browser.mjs"
   elif [[ "$mode" == inventory-menu-b ]]; then
     node "$QA_ROOT/scripts/qa/inventory-menu-b-browser.mjs"
   elif [[ "$mode" == cashier-gift-batch ]]; then
     if [[ ${QA_CORRECTIVE_SMOKE:-0} == 1 ]]; then node "$QA_ROOT/scripts/qa/corrective-batch-browser.mjs"; else node "$QA_ROOT/scripts/qa/cashier-gift-batch-browser.mjs"; fi
   elif [[ "$mode" == purchase-approval ]]; then node "$QA_ROOT/scripts/qa/purchase-approval-browser.mjs"; elif [[ "$mode" == purchase-invoice-hotfix ]]; then node "$QA_ROOT/scripts/qa/purchase-invoice-hotfix-browser.mjs"; elif [[ "$mode" == pos-quantity-hotfix ]]; then node "$QA_ROOT/scripts/qa/pos-quantity-browser.mjs"; elif [[ "$mode" == pos-smart-returns ]]; then node "$QA_ROOT/scripts/qa/pos-smart-returns-browser.mjs"; elif [[ "$mode" == batch-browser ]]; then node "$QA_ROOT/scripts/qa/v2-current-batch-browser.mjs"; elif [[ "$mode" == batch-e2e ]]; then node "$QA_ROOT/scripts/qa/v2-current-batch-e2e.mjs"; elif [[ "$mode" == v2-core-polish ]]; then node "$QA_ROOT/scripts/qa/v2-core-polish-browser.mjs"; elif [[ "$mode" == pos-reference ]]; then node "$QA_ROOT/scripts/qa/pos-reference-browser.mjs"; elif [[ "$mode" == pos-theme-r8 ]]; then node "$QA_ROOT/scripts/qa/pos-theme-r8-browser.mjs"; elif [[ "$mode" == pos-completion ]]; then node "$QA_ROOT/scripts/qa/pos-completion-browser.mjs"; elif [[ "$mode" == post-pos-repairs ]]; then node "$QA_ROOT/scripts/qa/post-pos-repairs-browser.mjs"; elif [[ "$mode" == commercial-ux ]]; then node "$QA_ROOT/scripts/qa/commercial-ux-browser.mjs"; else node "${QA_BROWSER_SCRIPT:-$QA_ROOT/scripts/qa/browser-smoke.mjs}"; fi
   ;;
 export) "$QA_PHP" "$QA_ROOT/scripts/qa/export-check.php" ;;
 backup) "$QA_PHP" "$QA_ROOT/scripts/qa/backup-fixture.php" "$work/backup.zip"; QA_BACKUP_ARCHIVE="$work/backup.zip" "$QA_ROOT/scripts/qa/backup-restore.sh" ;;
 *) echo "Unknown mode: $mode" >&2; exit 2;;
esac
