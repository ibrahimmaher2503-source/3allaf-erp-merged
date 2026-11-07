#!/usr/bin/env bash
# Source before any QA command. Never consume a worktree's production environment.
QA_ROOT=${QA_ROOT:-$(git rev-parse --show-toplevel)}
export QA_ROOT
cd "$QA_ROOT"
[[ ! -f .env && ! -f bootstrap/cache/config.php ]] || { echo 'FAIL QA requires a worktree without .env or cached runtime configuration' >&2; exit 1; }
export QA_PHP=${QA_PHP:-$(if [[ -x /opt/cpanel/ea-php84/root/usr/bin/php ]]; then echo /opt/cpanel/ea-php84/root/usr/bin/php; else command -v php; fi)}
export PATH="/opt/cpanel/ea-nodejs20/bin:$QA_ROOT/vendor/bin:$QA_ROOT/node_modules/.bin:$PATH"
unset APP_CONFIG_CACHE APP_SERVICES_CACHE APP_PACKAGES_CACHE APP_ROUTES_CACHE APP_EVENTS_CACHE
unset QA_DISPOSABLE_RUNTIME QA_STORAGE_PATH LARAVEL_STORAGE_PATH
export APP_ENV=qa APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= APP_DEBUG=false
export DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=1 DB_DATABASE=qa_rajh_disconnected DB_USERNAME=qa DB_PASSWORD= DB_URL= DB_SOCKET=
export SESSION_DRIVER=array CACHE_STORE=array QUEUE_CONNECTION=sync MAIL_MAILER=array
export LOG_CHANNEL=stderr

if [[ -z ${QA_CHROMIUM:-} ]]; then
  QA_CHROMIUM=$(node --input-type=module -e 'import {chromium} from "playwright";console.log(chromium.executablePath())')
  if [[ ! -x "$QA_CHROMIUM" && -x /home/rajeh_ahmed/.cache/rajeh-v021-hotfix3/chrome ]]; then QA_CHROMIUM=/home/rajeh_ahmed/.cache/rajeh-v021-hotfix3/chrome; fi
  export QA_CHROMIUM
fi
