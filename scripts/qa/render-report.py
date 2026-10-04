#!/usr/bin/env python3
from __future__ import annotations
import csv, json, pathlib, sys, os
from datetime import datetime, timezone
source = pathlib.Path(sys.argv[1])
json_path = pathlib.Path(sys.argv[2])
md_path = pathlib.Path(sys.argv[3])
rows=[]
with source.open(newline='') as handle:
    for row in csv.DictReader(handle, delimiter='\t'):
        rows.append(row)
counts={s:sum(1 for row in rows if row['status']==s) for s in ('PASS','PASS WITH WARNINGS','FAIL','SKIPPED')}
status='FAIL' if counts['FAIL'] else ('PASS WITH WARNINGS' if counts['PASS WITH WARNINGS'] or counts['SKIPPED'] else 'PASS')
payload={'application_tree':os.environ.get('QA_APPLICATION_TREE'),'elapsed_seconds':int(os.environ.get('QA_GATE_DURATION',0)),'mode':os.environ.get('QA_GATE_MODE'),'status':status,'generated_at':datetime.now(timezone.utc).isoformat(),'checks':rows,'counts':counts}
json_path.write_text(json.dumps(payload, indent=2, ensure_ascii=False)+'\n')
lines=[f"# Rajeh ERP QA Gate — {status}", '', f"Generated: {payload['generated_at']}", '', '| Check | Result | Duration (s) | Detail |', '|---|---|---:|---|']
for row in rows:
    detail=row['detail'].replace('|','\\|').replace('\n',' ')
    lines.append(f"| {row['name']} | {row['status']} | {row['seconds']} | {detail} |")
lines += ['', f"Passed: {counts['PASS']}", f"Warnings/skips: {counts['PASS WITH WARNINGS'] + counts['SKIPPED']}", f"Failed: {counts['FAIL']}", '', 'On-site boundary: physical printer, scanner, cash drawer, payment-terminal, receipt-output, and human UAT acceptance remain operator/client checks.']
md_path.write_text('\n'.join(lines)+'\n')
print(json.dumps({'status':status,'counts':counts}))
