#!/usr/bin/env bash
set -Eeuo pipefail

readonly active='/home/rajeh/laravel'
readonly baseline='/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix46-345c3af3f34a'
readonly candidate='/home/rajeh/releases/toyjoy/releases/toyjoy-v0.1.22-hotfix47-3ed4a9529c45'
readonly releases='/home/rajeh/releases/toyjoy/releases'
readonly shared_env='/home/rajeh/shared/toyjoy/.env'
readonly shared_storage='/home/rajeh/shared/toyjoy/storage'
readonly shared_public_storage='/home/rajeh/shared/toyjoy/storage/app/public'
readonly archive='/home/rajeh_ahmed/hotfix47-application.tar.gz'
readonly manifest='/home/rajeh_ahmed/hotfix47-application-manifest.sha256'
readonly archive_sha='255edc32ba4f727d458d2c4ac1faaf70a491da37c93fe710859188fb614d7aff'
readonly manifest_sha='b3ee60b214b584ff43faa87435c69709895da6c5e25f05a3234e51156981f389'
readonly baseline_commit='345c3af3f34a9e0ecd75e6694f3183f8f345cb99'
readonly baseline_tree='4c82cfcfb0b881c8ef69baacf2c1a1bb1cff49ca'
readonly candidate_commit='3ed4a9529c45806175424f70e8ce05993e8233cb'
readonly candidate_tree='991b9e5e0a65580fa8782479dbb1a12102e74343'
readonly php_bin='/opt/cpanel/ea-php84/root/usr/bin/php'
readonly fpm='ea-php84-php-fpm.service'
readonly tool_dir="$(cd "$(dirname "$0")" && pwd -P)"
readonly runtime_source="$tool_dir/runtime-controls.py"
readonly runtime_sha='b6cd97f7b8f05e63ae66d90aed0c781da58c25fa25628842c9862d41ad0fbd02'
readonly helper_source="$tool_dir/backup-runtime.php"
readonly helper_sha='1c517252dcbd4ffda29ec9c03de6d23ae09bee249f208d3e6db438ba91ae0321'
readonly trace_source="$tool_dir/trace-live-navigation.php"
readonly trace_sha='923931d2f35a54838280fece95cf8fd804140190e036d4589275251c320622d7'
readonly parent='/var/lib/toyjoy/hotfix47-deployment'

journal=''
runtime_state=''
plan=''
maintained=0
controls_started=0
switched=0
finished=0
isolated_user=''

fail(){ echo "HOTFIX47_DEPLOY=FAILED phase=${phase:-preflight} detail=$* journal=${journal:-uncreated}" >&2; return 1; }
sha(){ sha256sum "$1" | awk '{print $1}'; }
identity(){ sed -n "s/^$2=//p" "$1/RELEASE_IDENTITY"; }
prove_release(){
    local target=$1 commit=$2 tree=$3 version=$4
    [[ -d "$target" && ! -L "$target" && -f "$target/RELEASE_COMMIT" && "$(<"$target/RELEASE_COMMIT")" == "$commit" \
        && "$(identity "$target" commit)" == "$commit" && "$(identity "$target" tree)" == "$tree" \
        && "$(identity "$target" version)" == "$version" ]]
}
as_rajeh(){ local release=$1; shift; (cd "$release" && runuser -u rajeh -- env CACHE_STORE=array MAIL_MAILER=log "$@"); }
artisan(){ local release=$1; shift; as_rajeh "$release" "$php_bin" "$release/artisan" "$@" --no-interaction; }
controls(){ HF43_CONTROLLER_PID=$$ /usr/bin/python3 -I "$journal/runtime-controls.py" "$1" "$runtime_state"; }
switch_to(){
    local target=$1 pending="$active.hf47.$$"
    [[ ! -e "$pending" && ! -L "$pending" ]] || fail 'temporary active link already exists'
    ln -s "$target" "$pending"
    mv -Tf "$pending" "$active"
}
health(){
    local route code
    for route in /up /login; do
        code=$(curl --silent --show-error --proto '=https' --connect-timeout 10 --max-time 30 -o /dev/null -w '%{http_code}' "https://rajeh.xyz$route")
        [[ "$code" == 200 ]] || fail "health route=$route http=$code"
    done
}
record_state(){
    [[ -n "$journal" ]] || return 0
    jq -n --arg phase "$phase" --arg candidate "$candidate_commit" \
        --argjson maintained "$maintained" --argjson controls_started "$controls_started" \
        --argjson switched "$switched" --argjson finished "$finished" \
        '{phase:$phase,candidate:$candidate,maintained:($maintained==1),controls_started:($controls_started==1),switched:($switched==1),finished:($finished==1)}' \
        > "$journal/deployment-state.pending"
    chmod 600 "$journal/deployment-state.pending"
    mv -f "$journal/deployment-state.pending" "$journal/deployment-state.json"
}
stage(){ phase=$1; record_state; echo "HOTFIX47_STAGE=$phase journal=$journal"; }

recover(){
    local exit_code=$?
    [[ $finished -eq 0 ]] || return "$exit_code"
    trap - ERR INT TERM
    set +e
    if [[ -n "$isolated_user" ]]; then mysql --protocol=socket --socket="${db_socket:-/var/lib/mysql/mysql.sock}" -e "ALTER USER '$isolated_user'@'localhost' ACCOUNT LOCK;" >/dev/null 2>&1; fi
    if [[ $maintained -eq 1 || $controls_started -eq 1 || $switched -eq 1 ]]; then
        phase='recovery'; record_state
        artisan "$baseline" down --render=errors::503 --retry=60 >/dev/null 2>&1
        if [[ $controls_started -eq 1 ]]; then controls quiesce >/dev/null 2>&1; fi
        if [[ -L "$active" && "$(readlink -f "$active")" != "$baseline" ]]; then switch_to "$baseline"; fi
        systemctl reload "$fpm" >/dev/null 2>&1
        artisan "$baseline" up >/dev/null 2>&1
        if [[ $controls_started -eq 1 ]]; then controls resume >/dev/null 2>&1; fi
        health >/dev/null 2>&1
        maintained=0; controls_started=0; switched=0; phase='recovered'; record_state
        echo "HOTFIX47_RECOVERY=PASS baseline=$baseline_commit journal=$journal" >&2
    fi
    exit "$exit_code"
}
trap recover ERR INT TERM

[[ $EUID -eq 0 && $# -eq 0 ]] || fail 'run as root without arguments'
umask 077
phase='preflight'
(cd "$tool_dir" && sha256sum -c OPERATOR_TOOLS_MANIFEST.sha256 >/dev/null) || fail 'operator-tools manifest verification failed'
[[ -L "$active" && "$(readlink -f "$active")" == "$baseline" ]] || fail 'active release is not exact Hotfix46 path'
prove_release "$baseline" "$baseline_commit" "$baseline_tree" '0.1.22-hotfix46' || fail 'Hotfix46 baseline identity mismatch'
[[ "$(sha "$archive")" == "$archive_sha" && "$(sha "$manifest")" == "$manifest_sha" ]] || fail 'candidate package authentication failed'
[[ "$(sha "$runtime_source")" == "$runtime_sha" && "$(sha "$helper_source")" == "$helper_sha" && "$(sha "$trace_source")" == "$trace_sha" ]] || fail 'operator helper authentication failed'
if [[ -e "$candidate" || -L "$candidate" ]]; then
    prove_release "$candidate" "$candidate_commit" "$candidate_tree" '0.1.22-hotfix47' || fail 'unexpected existing candidate target'
fi
[[ -f "$shared_env" && -d "$shared_storage" && ! -L "$shared_storage" ]] || fail 'shared runtime paths unavailable'
[[ "$(systemctl show "$fpm" -p ActiveState --value)" == active ]] || fail 'PHP-FPM is not active'
health

mkdir -p "$parent"
chmod 0750 "$parent"
chown root:rajeh "$parent"
journal=$(mktemp -d "$parent/3ed4a9529c45-20260926.XXXXXX")
chmod 0750 "$journal"
chown root:rajeh "$journal"
runtime_state="$journal/runtime-state.json"
cp "$runtime_source" "$journal/runtime-controls.py"
cp "$helper_source" "$journal/backup-runtime.php"
cp "$trace_source" "$journal/trace-live-navigation.php"
chmod 0500 "$journal/runtime-controls.py"
chown root:rajeh "$journal/backup-runtime.php"
chmod 0640 "$journal/backup-runtime.php"
chmod 0600 "$journal/trace-live-navigation.php"
record_state

stage 'runtime-inventory'
controls inventory

stage 'candidate-preparation'
if [[ ! -e "$candidate" && ! -L "$candidate" ]]; then
    candidate_stage=$(mktemp -d "$releases/.hotfix47.XXXXXX")
    chmod 0700 "$candidate_stage"
    tar -xzf "$archive" -C "$candidate_stage"
    cmp -s "$manifest" "$candidate_stage/RELEASE_MANIFEST.sha256" || fail 'packaged manifest mismatch'
    (cd "$candidate_stage" && sha256sum -c RELEASE_MANIFEST.sha256 >/dev/null) || fail 'candidate payload verification failed'
    prove_release "$candidate_stage" "$candidate_commit" "$candidate_tree" '0.1.22-hotfix47' || fail 'candidate identity mismatch'
    [[ ! -e "$candidate_stage/.env" && ! -L "$candidate_stage/.env" ]] || fail 'candidate contains .env'
    if [[ -d "$candidate_stage/storage" && ! -L "$candidate_stage/storage" ]]; then find -P "$candidate_stage/storage" -depth -delete; fi
    [[ ! -e "$candidate_stage/public/storage" && ! -L "$candidate_stage/public/storage" ]] || fail 'candidate contains public/storage'
    ln -s "$shared_env" "$candidate_stage/.env"
    ln -s "$shared_storage" "$candidate_stage/storage"
    ln -s "$shared_public_storage" "$candidate_stage/public/storage"
    find -P "$candidate_stage" -path "$candidate_stage/bootstrap/cache" -prune -o -type d -exec chown root:root {} +
    find -P "$candidate_stage" -path "$candidate_stage/bootstrap/cache" -prune -o -type f -exec chown root:root {} +
    find -P "$candidate_stage" -type d -exec chmod a+rX {} +
    find -P "$candidate_stage" -path "$candidate_stage/bootstrap/cache" -prune -o -type f -exec chmod a+r,a-w {} +
    install -d -m 0770 -o rajeh -g rajeh "$candidate_stage/bootstrap/cache"
    mv "$candidate_stage" "$candidate"
fi
prove_release "$candidate" "$candidate_commit" "$candidate_tree" '0.1.22-hotfix47' || fail 'prepared candidate identity changed'
cmp -s "$manifest" "$candidate/RELEASE_MANIFEST.sha256" || fail 'prepared candidate manifest changed'
while read -r digest path; do
    [[ "$path" == ./storage/* ]] && continue
    [[ -f "$candidate/${path#./}" && ! -L "$candidate/${path#./}" && "$(sha "$candidate/${path#./}")" == "$digest" ]] || fail "prepared candidate payload changed: $path"
done < "$manifest"
[[ -L "$candidate/.env" && "$(readlink "$candidate/.env")" == "$shared_env" \
    && -L "$candidate/storage" && "$(readlink "$candidate/storage")" == "$shared_storage" \
    && -L "$candidate/public/storage" && "$(readlink "$candidate/public/storage")" == "$shared_public_storage" ]] || fail 'prepared candidate runtime links changed'
as_rajeh "$candidate" test -r "$candidate/vendor/autoload.php"

backup="$journal/backup"
install -d -m 0700 -o rajeh -g rajeh "$backup"
plan="$journal/backup-plan-private.json"
as_rajeh "$baseline" "$php_bin" "$journal/backup-runtime.php" plan "$baseline" "$backup" > "$plan"
chmod 0600 "$plan"
db_socket=$(jq -r '.server.socket' "$plan")
[[ "$db_socket" == /* && -S "$db_socket" ]] || fail 'verified local MariaDB socket unavailable'

stage 'writer-quiescence'
maintained=1; controls_started=1; record_state
artisan "$baseline" down --render=errors::503 --retry=60
controls quiesce
systemctl reload "$fpm"
controls verify
[[ -f "$baseline/storage/framework/down" ]] || fail 'maintenance fence is absent'
app_db_user=$(jq -r '.database_username' "$plan")
db_user_hex=$("$php_bin" -r 'echo bin2hex($argv[1]);' "$app_db_user")
open_connections=$(mysql --protocol=socket --socket="$db_socket" --batch --skip-column-names -e "SELECT COUNT(*) FROM information_schema.processlist WHERE ID <> CONNECTION_ID() AND (DB='rajeh_toyjoy' OR (DB IS NULL AND USER=CONVERT(0x$db_user_hex USING utf8mb4)));" )
[[ "$open_connections" == 0 ]] || fail 'uncontrolled Rajeh database sessions remain'

stage 'encrypted-backup'
as_rajeh "$baseline" "$php_bin" "$journal/backup-runtime.php" backup "$baseline" "$backup"

stage 'isolated-restore'
as_rajeh "$baseline" "$php_bin" "$journal/backup-runtime.php" restore-files "$baseline" "$backup"
token=$(openssl rand -hex 9)
isolated_db="toyjoy_hf47_restore_$token"
isolated_user="hf47_$token"
isolated_password=$(openssl rand -hex 32)
printf '%s\n' "$isolated_db" > "$journal/isolated-database-name"
mysql --protocol=socket --socket="$db_socket" -e "CREATE DATABASE \`$isolated_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '$isolated_user'@'localhost' IDENTIFIED BY '$isolated_password'; GRANT ALL ON \`$isolated_db\`.* TO '$isolated_user'@'localhost';"
jq -n --arg database "$isolated_db" --arg username "$isolated_user" --arg password "$isolated_password" --arg socket "$db_socket" \
    '{database:$database,username:$username,password:$password,socket:$socket}' > "$backup/isolated.json"
chown rajeh:rajeh "$backup/isolated.json"; chmod 0600 "$backup/isolated.json"
restore_cnf="$journal/restore-client.cnf"
printf '[client]\nuser=%s\npassword=%s\ndatabase=%s\n' "$isolated_user" "$isolated_password" "$isolated_db" > "$restore_cnf"
chmod 0600 "$restore_cnf"
mysql --defaults-extra-file="$restore_cnf" --protocol=socket --socket="$db_socket" --binary-mode=1 < "$(<"$backup/sql-path")"
as_rajeh "$baseline" "$php_bin" "$journal/backup-runtime.php" reconcile-db "$baseline" "$backup"
mysql --protocol=socket --socket="$db_socket" -e "ALTER USER '$isolated_user'@'localhost' ACCOUNT LOCK;"
isolated_user=''
rm -f "$restore_cnf" "$backup/isolated.json"
cp "$(<"$backup/archive-path")" "$journal/consistent-database-and-private-storage.zip"
chmod 0600 "$journal/consistent-database-and-private-storage.zip"

stage 'candidate-cache'
views="$candidate/bootstrap/cache/hotfix47-views"
install -d -m 0770 -o rajeh -g rajeh "$views"
find "$candidate/bootstrap/cache" -maxdepth 1 -type f \( -name 'config.php' -o -name 'events.php' -o -name 'routes-*.php' \) -delete
(cd "$candidate" && runuser -u rajeh -- env VIEW_COMPILED_PATH="$views" "$php_bin" artisan optimize --no-interaction)
controls verify

stage 'atomic-activation'
switch_to "$candidate"
switched=1; record_state
systemctl reload "$fpm"
prove_release "$(readlink -f "$active")" "$candidate_commit" "$candidate_tree" '0.1.22-hotfix47' || fail 'active candidate identity mismatch'

stage 'live-navigation-verification'
artisan "$candidate" up
maintained=0; record_state
health
controls resume
controls_started=0; record_state
health
"$php_bin" "$journal/trace-live-navigation.php" "$candidate" 2
"$php_bin" "$journal/trace-live-navigation.php" "$candidate" 1

phase='complete'; finished=1; record_state
trap - ERR INT TERM
echo "HOTFIX47_DEPLOY=SUCCESS candidate=$candidate_commit journal=$journal isolated_database=$isolated_db backup_restore=passed navigation=passed"
