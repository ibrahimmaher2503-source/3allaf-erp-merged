#!/usr/bin/env bash
set -Eeuo pipefail
mode=${1:-fast}
source "$(dirname "$0")/environment.sh"
"$QA_PHP" -r 'if(PHP_VERSION_ID<80400)exit(1);foreach(["bcmath","pdo_mysql","zip","dom"] as $e)if(!extension_loaded($e))exit(1);echo "PASS PHP ".PHP_VERSION." with required extensions\n";'
node -e 'if(Number(process.versions.node.split(".")[0])<20)process.exit(1);console.log("PASS Node "+process.versions.node)'
for tool in mariadb mariadbd mariadb-install-db python3 curl; do command -v "$tool" >/dev/null || { echo "FAIL missing $tool";exit 1; }; done
[[ -x vendor/bin/phpstan && -x vendor/bin/phpunit && -x node_modules/.bin/playwright ]]
node --input-type=module -e 'import {chromium} from "playwright";import {accessSync} from "node:fs";accessSync(process.env.QA_CHROMIUM||chromium.executablePath());console.log("PASS browser executable available")'
if [[ "$mode" == release ]]; then command -v semgrep >/dev/null;[[ -x vendor/bin/infection ]]; fi
[[ -f public/build/manifest.json ]] || { echo 'FAIL build assets unavailable; prepare once with npm run build';exit 1; }
echo 'PASS required gate tools and assets are available'
