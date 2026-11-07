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
[[ ! -e "$candidate" && ! -L "$candidate" && ! -e "$journal" ]]
mkdir -m 0755 "$candidate"
tar -xzf "$archive" -C "$candidate"
python3 - "$candidate" "$manifest" <<'PY'
import sys,json,hashlib
from pathlib import Path
root=Path(sys.argv[1]);data=json.loads(Path(sys.argv[2]).read_text())
assert not (root/'.env').exists() and not (root/'storage').exists()
for name,expected in data['files'].items():
 p=root/name
 assert p.is_file() and not p.is_symlink() and hashlib.sha256(p.read_bytes()).hexdigest()==expected,name
assert (root/'vendor/autoload.php').is_file() and (root/'public/build/manifest.json').is_file()
print('Authenticated manifest files verified:',data['file_count'])
PY
ln -s "$shared_env" "$candidate/.env"
ln -s "$shared_storage" "$candidate/storage"
ln -s "$shared_public" "$candidate/public/storage"
[[ ! -e "$candidate/storage/storage" && ! -L "$candidate/storage/storage" ]]
find "$candidate" -type d -exec chown root:root {} +
find "$candidate" -type f -exec chown root:root {} +
find "$candidate" -type d -exec chmod a+rX {} +
find "$candidate" -type f -exec chmod a+r,a-w {} +
install -d -m 0770 -o rajeh -g rajeh "$candidate/bootstrap/cache" "$candidate/bootstrap/cache/views"
runuser -u rajeh -- env APP_ENV=production CACHE_STORE=array MAIL_MAILER=log VIEW_COMPILED_PATH="$candidate/bootstrap/cache/views" "$php" "$candidate/artisan" optimize --no-interaction
# Livewire island source files are generated under shared storage at request
# time. Clear only those generated caches while maintenance is active, then
# recreate them for the FPM account. This prevents a root-run warmup or copied
# release cache from leaving files whose mtime cannot be changed by FPM.
for runtime_cache in "$shared_storage/framework/views/livewire" "$shared_storage/framework/views/blaze"; do
    install -d -m 0770 -o rajeh -g rajeh "$runtime_cache"
    find "$runtime_cache" -mindepth 1 -delete
    chown -R rajeh:rajeh "$runtime_cache"
    chmod -R u+rwX,g+rwX,o-rwx "$runtime_cache"
done
runuser -u rajeh -- env CACHE_STORE=array MAIL_MAILER=log "$php" -r '
$root=$argv[1];require $root."/vendor/autoload.php";$app=require $root."/bootstrap/app.php";$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$paths=[config("logging.channels.single.path"),config("view.compiled"),config("session.files"),config("cache.stores.file.path"),config("filesystems.disks.local.root"),config("backup.backup.temporary_directory")];
foreach($paths as $path){if(!is_string($path)||str_contains($path,".audit-")||(!str_starts_with($path,$root."/")&&!str_starts_with($path,"/home/rajeh/shared/toyjoy/")))throw new RuntimeException("Runtime cache path rejected.");}
if(App\Support\ApplicationVersion::RELEASE!=="0.1.22-qa-phase2")throw new RuntimeException("Version mismatch.");
if(!(str_starts_with((new ReflectionClass(App\Modules\Platform\Support\AuditLogPresentation::class))->getFileName(),$root."/")))throw new RuntimeException("Wrong release autoload root.");
echo "Final-root cache paths, version and autoload verified.\n";' "$candidate"
mkdir -m 0700 "$journal"
cp "$manifest" "$journal/manifest.json"
printf 'candidate=%s\nbaseline=%s\ncommit=%s\ntree=%s\narchive_sha=%s\nmanifest_sha=%s\n' "$candidate" "$baseline" "$commit" "$tree" "$archive_sha" "$manifest_sha" > "$journal/release.txt"
python3 - "$journal" <<'PYLOG'
import pathlib,json,sys
p=pathlib.Path('/home/rajeh/shared/toyjoy/storage/logs/laravel.log');v={'path':str(p),'inode':p.stat().st_ino,'offset':p.stat().st_size};pathlib.Path(sys.argv[1],'log-start.json').write_text(json.dumps(v)+'\n')
PYLOG
cp /tmp/qa-phase2-backup.log "$journal/predeployment-backup.txt"
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
bash /tmp/qa-phase2-smoke.sh > "$journal/smoke-run.log" 2>&1
printf 'active=%s\nmaintenance=off\nactivation=success\ncompact_smoke=PASS\n' "$candidate" > "$journal/activation.txt"
finish=1
trap - ERR INT TERM
echo "ACTIVATION=SUCCESS commit=$commit journal=$journal"
