#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
active=/home/rajeh/laravel
baseline=/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-qa-phase2-3821af5
candidate=/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-cost-optional-1d27f7b
archive=/tmp/rajeh-product-cost-1d27f7b.tar.gz
manifest=/tmp/rajeh-product-cost-1d27f7b.manifest.json
archive_sha=153a9908916e6d46c1be9d8ad46116deadfbaec565b1df9314b8a26f828d9f12
manifest_sha=7b7234090ac5a45e441b0bab32234d55c4d08f94dc1789c3b419563e414065f3
commit=1d27f7b33b1536c10ddc7083a5eac452ce1b9c06
tree=85131b3960686cdbb5357f1c68dd03631b6ef679
shared_env=/home/rajeh/shared/toyjoy/.env
shared_storage=/home/rajeh/shared/toyjoy/storage
shared_public=/home/rajeh/shared/toyjoy/storage/app/public
journal=/var/lib/toyjoy/product-cost-optional-1d27f7b
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
[[ ! -f /home/rajeh/shared/toyjoy/storage/framework/down ]]
cp "$journal/activation.txt" "$journal/activation-attempt1.txt"
printf 'result=ROLLED_BACK\nreason=smoke-helper-not-readable-by-runtime-account\nrollback=%s\n' "$baseline" > "$journal/attempt1-result.txt"
python3 - "$candidate" "$manifest" <<'PYREUSE'
import json,pathlib,sys,hashlib
root=pathlib.Path(sys.argv[1]);m=json.loads(pathlib.Path(sys.argv[2]).read_text())
for name in m['runtime_changes']+['RELEASE_COMMIT','RELEASE_IDENTITY']:
 assert hashlib.sha256((root/name).read_bytes()).hexdigest()==m['files'][name],name
print('Prepared immutable runtime and identity unchanged; validated final-root caches reused.')
PYREUSE
# No smoke row ran before the helper permission failure. Run the outstanding compact smoke once.
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
printf 'active=%s\nmaintenance=off\nactivation=success\naffected_runtime_checks=verified_product_cost_A_F_browser_import_accounting\nfinal_compact_smoke=pending\n' "$candidate" > "$journal/activation.txt"
bash /tmp/product-cost-smoke.sh > "$journal/smoke-run.log" 2>&1
printf 'active=%s\nmaintenance=off\nactivation=success\ncompact_smoke=PASS\n' "$candidate" > "$journal/activation.txt"
finish=1
trap - ERR INT TERM
echo "ACTIVATION=SUCCESS commit=$commit journal=$journal"
