#!/usr/bin/env bash
set -Eeuo pipefail
php=/opt/cpanel/ea-php85/root/usr/bin/php
runtime=/home/rajeh/laravel
journal=/var/lib/toyjoy/product-cost-optional-1d27f7b
runuser -u rajeh -- env CACHE_STORE=array MAIL_MAILER=log "$php" /tmp/product-cost-read-smoke.php health > "$journal/smoke-health.json"
for spec in '1 /admin 200' '1 /catalog/products/create 200' '1 /approvals 200' '1 /inventory/balances 200' '1 /pos/shift 200' '3 /admin/translations 403';do
 read -r actor uri code <<< "$spec"
 runuser -u rajeh -- env CACHE_STORE=array MAIL_MAILER=log "$php" /tmp/product-cost-read-smoke.php route "$actor" "$uri" "$code" >> "$journal/smoke-routes.jsonl"
done
for spec in '/up 200' '/login 200' '/admin 302';do
 read -r uri expected <<< "$spec"
 code=$(curl --silent --show-error --proto '=https' --connect-timeout 10 --max-time 25 -o /dev/null -w '%{http_code}' "https://rajeh.xyz$uri")
 printf '%s %s\n' "$uri" "$code" >> "$journal/smoke-http.txt"
 [[ "$code" == "$expected" ]]
done
systemctl is-active ea-php85-php-fpm.service crond > "$journal/smoke-services.txt"
crontab -u rajeh -l | grep -E 'artisan (queue:work|schedule:run)' > "$journal/smoke-cron.txt"
[[ $(wc -l < "$journal/smoke-cron.txt") -eq 2 ]]
runuser -u rajeh -- env CACHE_STORE=array MAIL_MAILER=log "$php" "$runtime/artisan" backup:monitor --no-interaction > "$journal/smoke-backup-monitor.txt"
python3 - "$journal" <<'PY'
import json,pathlib,re,sys
j=pathlib.Path(sys.argv[1]);state=json.loads((j/'log-start.json').read_text());p=pathlib.Path(state['path']);assert p.stat().st_ino==state['inode'],'Log rotated; inspect scoped recent log'
with p.open('rb') as f:f.seek(state['offset']);data=f.read().decode(errors='replace')
critical=[line[:240] for line in data.splitlines() if re.search(r'production\.(?:ERROR|CRITICAL|ALERT|EMERGENCY):',line)]
(j/'smoke-log-summary.json').write_text(json.dumps({'new_bytes':len(data.encode()),'new_error_or_critical_count':len(critical),'result':'PASS' if not critical else 'FAIL'},indent=2)+'\n')
assert not critical,'New production errors found during scoped activation/smoke'
PY
printf 'COMPACT_SMOKE=PASS\n'
