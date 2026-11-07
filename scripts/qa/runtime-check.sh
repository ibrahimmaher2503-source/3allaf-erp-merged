#!/usr/bin/env bash
set -Eeuo pipefail
source "$(dirname "$0")/environment.sh"
"$QA_PHP" scripts/qa/console.php schedule:list --no-ansi
"$QA_PHP" scripts/qa/console.php about --only=environment --no-ansi
"$QA_PHP" -r '$app=require "scripts/qa/bootstrap.php"; foreach (["queue.default"=>"sync","cache.default"=>"array","session.driver"=>"array","database.default"=>"mysql"] as $key=>$want) { if(config($key)!==$want) throw new RuntimeException("Unsafe QA configuration: ".$key); } echo "PASS isolated queue/cache/session/database configuration\n";'
