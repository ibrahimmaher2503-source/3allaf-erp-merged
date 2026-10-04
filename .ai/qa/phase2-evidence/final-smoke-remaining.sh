#!/usr/bin/env bash
set -Eeuo pipefail
php=/opt/cpanel/ea-php85/root/usr/bin/php
runtime=/home/rajeh/laravel
journal=/var/lib/toyjoy/qa-phase2-3821af5
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
