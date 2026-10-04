#!/usr/bin/env python3
"""Transparent raw and individually reviewed scores; no blanket mutator ignores."""
import difflib,hashlib,json,pathlib,sys
root=pathlib.Path(__file__).resolve().parents[2]
x=json.loads(pathlib.Path(sys.argv[1]).read_text())
manifest=json.loads((root/'.ai/qa/phase2-mutation-review.json').read_text())
review={r['fingerprint']:r for r in manifest['mutants']}
excluded=[];remaining=[]
for entry in x['escaped']:
 m=entry['mutator'];file='app/'+m['originalFilePath'].split('/app/')[1]
 diff=difflib.unified_diff(m['originalSourceCode'].splitlines(),m['mutatedSourceCode'].splitlines(),n=0)
 patch='\n'.join(z for z in diff if z[:1] in '+-' and z[:3] not in ('+++','---'))
 fingerprint=hashlib.sha256(json.dumps([file,m['originalStartLine'],m['mutatorName'],patch],separators=(',',':')).encode()).hexdigest()
 r=review.get(fingerprint)
 if r and r['ignored'] and r['classification'] in ['equivalent mutant','unreachable/dead code'] and r['reason'] and r['source_sha256']==hashlib.sha256((root/file).read_bytes()).hexdigest():excluded.append(fingerprint)
 else:remaining.append(fingerprint)
n=x['stats']['totalMutantsCount'];covered=n-x['stats'].get('notCoveredCount',0);k=len(x['killed']);raw=round(k/covered*100,2) if covered else 0;adjusted=round(k/(covered-len(excluded))*100,2) if covered>len(excluded) else 0
result={'total':n,'covered':covered,'killed':k,'raw_survivors':len(x['escaped']),'errors':x['stats'].get('errorCount',0),'syntax_errors':x['stats'].get('syntaxErrorCount',0),'timeouts':x['stats'].get('timeOutCount',0),'infection_ignored':x['stats'].get('ignoredCount',0),'skipped':x['stats'].get('skippedCount',0),'reviewed_exclusions':len(excluded),'retained_survivors':len(remaining),'raw_msi':raw,'adjusted_msi':adjusted,'excluded_fingerprints':excluded,'retained_fingerprints':remaining}
out=pathlib.Path(sys.argv[2]) if len(sys.argv)>2 else root/'.ai/qa/runs/mutation-score.json';out.parent.mkdir(parents=True,exist_ok=True);out.write_text(json.dumps(result,indent=2)+'\n')
print(f"Critical mutation: covered {covered}/{n}, killed {k}, raw survivors {len(x['escaped'])}, errors {result['errors']}, ignored by Infection {result['infection_ignored']}")
print(f"Raw covered MSI {raw}%; individually reviewed exclusions {len(excluded)}; retained survivors {len(remaining)}; adjusted MSI {adjusted}% (minimum 85%, preferred 90%)")
if result['errors'] or result['syntax_errors'] or result['timeouts'] or result['infection_ignored'] or covered==0 or adjusted<85:sys.exit(1)
if raw<85:print('WARN raw MSI remains below 85%; adjusted score excludes only documented equivalent/unreachable patches, all raw survivors remain visible');sys.exit(77)
