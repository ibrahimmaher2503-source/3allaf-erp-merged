#!/usr/bin/env bash
set -Eeuo pipefail
root=${QA_ROOT:-$(git rev-parse --show-toplevel)}
[[ "$DB_SOCKET" == /tmp/rajeh-qa-mariadb.* && "$DB_DATABASE" == qa_rajh_* ]]
work=$(mktemp -d /tmp/rajeh-qa-backup.XXXXXX)
trap 'rm -rf -- "$work"' EXIT
"$QA_PHP" "$root/scripts/qa/console.php" platform:backup:restore "$QA_BACKUP_ARCHIVE" "$work/restore"
[[ -f "$work/restore/qa.sql" ]]
restore_db="qa_rajh_restore_$RANDOM"
printf 'Disposable restore database (before creation): %s\n' "$restore_db"
[[ ${#restore_db} -lt 60 ]]
mariadb --no-defaults --socket="$DB_SOCKET" -uroot -e "CREATE DATABASE \`$restore_db\`;"
mariadb --no-defaults --socket="$DB_SOCKET" -uroot "$restore_db" < "$work/restore/qa.sql"
QA_RESTORE_DATABASE="$restore_db" "$QA_PHP" -r '$pdo=new PDO("mysql:unix_socket=".getenv("DB_SOCKET").";dbname=".getenv("QA_RESTORE_DATABASE"),"root","");$expected=json_decode(file_get_contents(getenv("QA_STORAGE")."/backup-counts.json"),true);foreach($expected as $table=>$count)if((int)$pdo->query("SELECT COUNT(*) FROM `".$table."`")->fetchColumn()!==$count)throw new RuntimeException("Restore mismatch ".$table);echo "PASS restored populated QA database counts across seven catalog/financial/stock tables\n";'
mariadb --no-defaults --socket="$DB_SOCKET" -uroot -e "DROP DATABASE \`$restore_db\`;"
