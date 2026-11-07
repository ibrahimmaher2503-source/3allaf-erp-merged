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
runuser -u rajeh -- env CACHE_STORE=array MAIL_MAILER=log "$php" -r '
$root=$argv[1];require $root."/vendor/autoload.php";$app=require $root."/bootstrap/app.php";$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$paths=[config("logging.channels.single.path"),config("view.compiled"),config("session.files"),config("cache.stores.file.path"),config("filesystems.disks.local.root"),config("backup.backup.temporary_directory")];
foreach($paths as $path){if(!is_string($path)||str_contains($path,".audit-")||(!str_starts_with($path,$root."/")&&!str_starts_with($path,"/home/rajeh/shared/toyjoy/")))throw new RuntimeException("Runtime cache path rejected.");}
if(App\Support\ApplicationVersion::RELEASE!=="0.1.22-cost-optional")throw new RuntimeException("Version mismatch.");
if(!(str_starts_with((new ReflectionClass(App\Modules\Platform\Support\AuditLogPresentation::class))->getFileName(),$root."/")))throw new RuntimeException("Wrong release autoload root.");
if(app(App\Modules\Catalog\Support\ProductPricingContract::class)->forProductCard(null,"25")!==["average_cost"=>null,"sale_price"=>"25.0000"])throw new RuntimeException("Optional cost contract mismatch.");
$component=app("livewire.compiler")->compile($root."/resources/views/catalog/product-form.blade.php");
$compiled=file_get_contents((new ReflectionClass($component))->getFileName());
if(!str_contains($compiled,"costRules")||str_contains($compiled,chr(36)."savedCost"))throw new RuntimeException("Compiled Product Card cost rule is stale.");
echo "Final-root cache paths, version, autoload and optional compiled cost rule verified.\n";' "$candidate"
mkdir -m 0700 "$journal"
cp "$manifest" "$journal/manifest.json"
printf 'candidate=%s\nbaseline=%s\ncommit=%s\ntree=%s\narchive_sha=%s\nmanifest_sha=%s\n' "$candidate" "$baseline" "$commit" "$tree" "$archive_sha" "$manifest_sha" > "$journal/release.txt"
python3 - "$journal" <<'PYLOG'
import pathlib,json,sys
p=pathlib.Path('/home/rajeh/shared/toyjoy/storage/logs/laravel.log');v={'path':str(p),'inode':p.stat().st_ino,'offset':p.stat().st_size};pathlib.Path(sys.argv[1],'log-start.json').write_text(json.dumps(v)+'\n')
PYLOG
cp /tmp/product-cost-backup.log "$journal/predeployment-backup.txt"
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
