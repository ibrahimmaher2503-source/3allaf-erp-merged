#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
active=/home/rajeh/laravel
baseline=/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-audit-final5-ff75916
candidate=/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-qa-phase2-3821af5
archive=/tmp/rajeh-qa-phase2-3821af5.tar.gz
manifest=/tmp/rajeh-qa-phase2-3821af5.manifest.json
archive_sha=90c0d828b45595033aacec244973f622fd4d1ebb9e96138b224e92aac8d1dd71
manifest_sha=03cdd9c7ff194ca0eb923878d58a8755936746dc8ac9fba9f82291404e44ccc9
commit=3821af553daca932e36c8a49ab78f911e1cf2379
tree=756badffe9b5dfc6e903383044b48281717b083f
shared_env=/home/rajeh/shared/toyjoy/.env
shared_storage=/home/rajeh/shared/toyjoy/storage
shared_public=/home/rajeh/shared/toyjoy/storage/app/public
journal=/var/lib/toyjoy/qa-phase2-3821af5
php=/opt/cpanel/ea-php85/root/usr/bin/php
maintained=0
switched=0
finish=0
recover(){
 rc=$?;if [[ $finish -eq 1 ]];then exit "$rc";fi
 set +e
 if [[ $switched -eq 1 ]];then ln -s "$baseline" "$active.audit-recovery.$$" && mv -Tf "$active.audit-recovery.$$" "$active";systemctl reload ea-php85-php-fpm.service;fi
 if [[ $maintained -eq 1 ]];then runuser -u rajeh -- env CACHE_STORE=array MAIL_MAILER=log "$php" "$baseline/artisan" up --no-interaction;fi
 echo "ACTIVATION_FAILED rollback=$baseline rc=$rc" >&2;exit "$rc"
}
trap recover ERR INT TERM
[[ $EUID -eq 0 ]]
[[ -L "$active" && "$(readlink -f "$active")" == "$baseline" ]]
[[ "$(sha256sum "$archive" | awk '{print $1}')" == "$archive_sha" ]]
[[ "$(sha256sum "$manifest" | awk '{print $1}')" == "$manifest_sha" ]]
[[ -f "$shared_env" && -d "$shared_storage" && -d "$shared_public" ]]
[[ "$(systemctl show ea-php85-php-fpm.service -p ActiveState --value)" == active ]]
[[ -d "$candidate" && -d "$journal" ]]
[[ -d "$candidate" && -d "$journal" ]]
[[ ! -f /home/rajeh/shared/toyjoy/storage/framework/down ]]
cp "$journal/activation.txt" "$journal/activation-attempt1.txt"
printf 'result=ROLLED_BACK\nreason=smoke-helper-root-PATH-missing-rg\nrollback=%s\n' "$baseline" > "$journal/attempt1-result.txt"
# Reuse the prepared immutable candidate, authenticated manifest, final-root
# caches and successful read-only smoke rows; no repeated full verification.
python3 - "$candidate" "$manifest" <<'PYREUSE'
import json,pathlib,sys,hashlib
root=pathlib.Path(sys.argv[1]);m=json.loads(pathlib.Path(sys.argv[2]).read_text())
for name in m['runtime_changes']+['RELEASE_COMMIT','RELEASE_IDENTITY']:
 assert hashlib.sha256((root/name).read_bytes()).hexdigest()==m['files'][name],name
print('Prepared runtime/identity unchanged; preserved validation reused.')
PYREUSE
maintained=1
runuser -u rajeh -- env CACHE_STORE=array MAIL_MAILER=log "$php" "$active/artisan" down --render=errors::503 --retry=60 --no-interaction
sleep 55
ln -s "$candidate" "$active.audit-final.$$"
mv -Tf "$active.audit-final.$$" "$active"
switched=1
systemctl reload ea-php85-php-fpm.service
[[ "$(readlink -f "$active")" == "$candidate" ]]
runuser -u rajeh -- env CACHE_STORE=array MAIL_MAILER=log "$php" "$candidate/artisan" up --no-interaction
maintained=0
printf 'active=%s\nmaintenance=off\nactivation=success\naffected_runtime_checks=preserved_phase2_tax_allocation_product_race\nfinal_compact_smoke=pending\n' "$candidate" > "$journal/activation.txt"
bash /tmp/qa-phase2-smoke-remaining.sh > "$journal/smoke-remaining.log" 2>&1
printf 'active=%s\nmaintenance=off\nactivation=success\ncompact_smoke=PASS_preserved_rows_plus_remaining_checks\n' "$candidate" > "$journal/activation.txt"
finish=1
trap - ERR INT TERM
echo "ACTIVATION=SUCCESS commit=$commit journal=$journal"
