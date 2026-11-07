<?php
declare(strict_types=1);

// External deployment helper: no application files or persisted config are modified.
try {
    [$script, $mode, $release, $work] = $argv;
    require $release.'/vendor/autoload.php';
    $app = require $release.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config(['cache.default'=>'array', 'queue.default'=>'sync']);
    $connection = config('database.default');
    $db = Illuminate\Support\Facades\DB::connection();
    if ($db->getDriverName() !== 'mysql' || $db->getDatabaseName() !== 'rajeh_toyjoy') {
        throw new RuntimeException('Unexpected production database identity');
    }
    if ($db->getConfig('read') || $db->getConfig('write')) throw new RuntimeException('Multiple database endpoints require separate writer and dump controls');
    $password = config('backup.backup.password');
    if (!is_string($password) || $password === '') throw new RuntimeException('Backup encryption is not configured');
    $private = realpath(storage_path('app/private'));
    if ($private === false) throw new RuntimeException('Private storage does not resolve');
    $name = config('backup.backup.name');
    if ($name !== 'TOY & JOY') throw new RuntimeException('Unexpected backup identity');
    $exclude = [];
    foreach (config('backup.backup.source.files.exclude', []) as $p) $exclude[] = realpath($p) ?: $p;
    foreach (config('backup.backup.destination.disks') as $disk) {
        if (config("filesystems.disks.$disk.driver") !== 'local') throw new RuntimeException('Nonlocal backup destination requires review');
        $root = realpath(config("filesystems.disks.$disk.root"));
        if ($root === false) throw new RuntimeException('Backup destination does not resolve');
        $exclude[] = realpath($root.'/'.$name) ?: $root.'/'.$name;
    }
    $oldTemporary = config('backup.backup.temporary_directory');
    if (is_string($oldTemporary) && $oldTemporary !== '') $exclude[] = realpath($oldTemporary) ?: $oldTemporary;
    $exclude[] = realpath($work) ?: $work;
    $exclude[] = realpath(storage_path('app/backup-temp')) ?: storage_path('app/backup-temp');
    $exclude = array_values(array_unique($exclude));
    $includes = [];
    foreach (config('backup.backup.source.files.include') as $p) {
        $r = realpath($p);
        if ($r === false) throw new RuntimeException('Backup source does not resolve');
        $includes[] = $r;
    }
    // Freeze and reconcile the exact file set selected by the maintained package.
    $selection = Spatie\Backup\Tasks\Backup\FileSelection::create($includes)->excludeFilesFrom($exclude)->shouldFollowLinks(false);
    $files = [];
    foreach ($selection->selectedFiles() as $p) {
        if (is_file($p)) {
            if (is_link($p)) throw new RuntimeException('Backup file symlink requires review');
            $files[ltrim($p, '/')] = hash_file('sha256', $p);
        }
    }
    ksort($files);
    $server = $db->selectOne('SELECT @@socket AS socket, @@hostname AS hostname, @@port AS port, @@server_id AS server_id');
    if ($mode === 'plan') {
        if (config('app.maintenance.driver', 'file') !== 'file') throw new RuntimeException('Configured maintenance driver requires a verified non-file writer fence');
        if (!extension_loaded('zip') || !ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, true)
            || !ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, false)) throw new RuntimeException('AES encryption/decryption unavailable');
        if ($db->selectOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=? AND table_type='BASE TABLE' AND engine <> 'InnoDB'", [$db->getDatabaseName()])->n > 0) {
            throw new RuntimeException('Single-transaction backup requires all tables to be InnoDB');
        }
        foreach (['routines', 'events', 'triggers', 'views'] as $kind) {
            $column = ['routines'=>'routine_schema', 'events'=>'event_schema', 'triggers'=>'trigger_schema', 'views'=>'table_schema'][$kind];
            if ($db->selectOne("SELECT COUNT(*) AS n FROM information_schema.$kind WHERE $column=?", [$db->getDatabaseName()])->n > 0) {
                throw new RuntimeException('Database routines/events/triggers/views need explicit dump, definer, and writer controls');
            }
        }
        echo json_encode(['private'=>$private, 'include'=>$includes, 'exclude'=>$exclude, 'server'=>$server,
            'database_username'=>$db->getConfig('username'),
            'app_url'=>rtrim(config('app.url'), '/')], JSON_THROW_ON_ERROR)."\n";
        exit;
    }
    if ($mode === 'backup') {
        file_put_contents($work.'/files-before.json', json_encode($files, JSON_THROW_ON_ERROR));
        config([
            'backup.backup.source.files.include'=>$includes,
            'backup.backup.source.files.exclude'=>$exclude,
            'backup.backup.source.files.follow_links'=>false,
            'backup.backup.source.files.relative_path'=>null,
            'backup.backup.source.databases'=>[$connection],
            'backup.backup.database_dump_compressor'=>null,
            'backup.backup.database_dump_filename_base'=>'database',
            'backup.backup.database_dump_file_timestamp_format'=>null,
            'backup.backup.database_dump_file_extension'=>'sql',
            'backup.backup.encryption'=>'aes256',
            'backup.backup.verify_backup'=>true,
            'backup.backup.temporary_directory'=>$work.'/temporary',
            'backup.backup.destination.disks'=>['hf42'],
            'filesystems.disks.hf42'=>['driver'=>'local', 'root'=>$work.'/encrypted', 'throw'=>true],
            "database.connections.$connection.unix_socket"=>$server->socket,
            "database.connections.$connection.dump"=>['useSingleTransaction'=>true, 'skipLockTables'=>true, 'useQuick'=>true],
        ]);
        $app->forgetInstance(Spatie\Backup\Config\Config::class);
        // A nested Artisan call replaces lastOutput with OutputStyle, which has
        // no fetch(). Capture the actual output directly, independent of lastOutput.
        $stream = fopen($work.'/backup-private.log', 'ab');
        if ($stream === false) throw new RuntimeException('Backup output capture cannot be opened');
        $output = new Symfony\Component\Console\Output\StreamOutput($stream,
            Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE, false);
        $logger = new Monolog\Logger('hotfix43_backup');
        $logger->pushHandler(new Monolog\Handler\StreamHandler($work.'/backup-events-private.log', Monolog\Logger::DEBUG));
        $app->make(Spatie\Backup\Support\BackupLogger::class)->useLogger($logger);
        $exit = null;
        try {
            $exit = Illuminate\Support\Facades\Artisan::call('platform:backup:run', [], $output);
        } finally {
            fflush($stream); fclose($stream);
            file_put_contents($work.'/backup-exit-status.json', json_encode(['exit'=>$exit,
                'output_log'=>$work.'/backup-private.log', 'events_log'=>$work.'/backup-events-private.log'], JSON_THROW_ON_ERROR));
        }
        if ($exit !== 0) throw new RuntimeException('Maintained backup command failed; output_log='.$work.'/backup-private.log events_log='.$work.'/backup-events-private.log');
        $archives = glob($work.'/encrypted/TOY & JOY/*.zip');
        if (count($archives) !== 1) throw new RuntimeException('Backup archive is missing or ambiguous');
        file_put_contents($work.'/archive-path', $archives[0]);
        exit;
    }
    if ($mode === 'restore-files') {
        $archive = trim(file_get_contents($work.'/archive-path'));
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true || !$zip->setPassword($password)) throw new RuntimeException('Encrypted archive cannot be opened');
        $expected = json_decode(file_get_contents($work.'/files-before.json'), true, 512, JSON_THROW_ON_ERROR);
        if ($expected !== $files) throw new RuntimeException('Source files changed during backup');
        $seen = []; $sql = [];
        for ($i=0; $i<$zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            $path = $entry['name'];
            if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || in_array('..', explode('/', $path), true)) throw new RuntimeException('Unsafe archive path');
            if (str_ends_with($path, '/')) continue;
            if (($entry['encryption_method'] ?? 0) !== ZipArchive::EM_AES_256) throw new RuntimeException('Archive entry is not AES-256 encrypted');
            if (isset($seen[$path])) throw new RuntimeException('Duplicate archive entry');
            $seen[$path] = true;
            $target = $work.'/restore/files/'.$path;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) throw new RuntimeException('Restore directory creation failed');
            $in = $zip->getStream($path);
            $out = fopen($target, 'xb');
            if ($in === false || $out === false || stream_copy_to_stream($in, $out) !== $entry['size']) throw new RuntimeException('Archive decryption/read failed');
            fclose($in); fclose($out);
            if (isset($expected[$path])) {
                if (hash_file('sha256', $target) !== $expected[$path]) throw new RuntimeException('Restored file hash mismatch');
                unset($expected[$path]);
            } elseif (preg_match('#^db-dumps/mysql-rajeh_toyjoy\.sql$#', $path)) {
                $sql[] = $target;
            } else throw new RuntimeException('Unexpected archive file');
        }
        $zip->close();
        if ($expected !== [] || count($sql) !== 1) throw new RuntimeException('Incomplete file or database restoration');
        // Dump provenance is authenticated; reject database switching and client shell directives.
        $handle = fopen($sql[0], 'rb');
        while (($line=fgets($handle)) !== false) {
            if (preg_match('/^\s*(USE\s|CREATE\s+DATABASE|DROP\s+DATABASE|SOURCE\s|SYSTEM\s|\\\\[!.])|DEFINER\s*=/i', $line)) throw new RuntimeException('Dump requires unsafe or privileged restore behavior');
        }
        fclose($handle);
        file_put_contents($work.'/sql-path', $sql[0]);
        file_put_contents($work.'/files-restored.json', json_encode($files, JSON_THROW_ON_ERROR));
        exit;
    }
    if ($mode === 'stock-fingerprint') {
        $pdo=$db->getPdo(); $result=[];
        foreach (['stock_balances','stock_movements'] as $table) {
            $quoted='`'.$table.'`';
            $schema=$pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
            $columns=$pdo->query('SHOW FULL COLUMNS FROM '.$quoted)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as &$column) unset($column['Privileges']);
            unset($column);
            $hash=hash_init('sha256'); $count=0;
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
            $query=$pdo->query('SELECT * FROM '.$quoted.' ORDER BY id');
            while (($row=$query->fetch(PDO::FETCH_NUM))!==false) {
                $row=array_map(static fn($v)=>$v===null ? null : (string)$v,$row);
                hash_update($hash,hash('sha256',serialize($row))."\n"); $count++;
            }
            $query->closeCursor();
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
            $result[$table]=['schema'=>hash('sha256',$schema),'columns'=>$columns,'count'=>$count,'rows'=>hash_final($hash)];
        }
        echo json_encode($result,JSON_THROW_ON_ERROR)."\n"; exit;
    }
    if ($mode === 'reconcile-db') {
        $c = json_decode(file_get_contents($work.'/isolated.json'), true, 512, JSON_THROW_ON_ERROR);
        if (!preg_match('/^toyjoy_hf47_restore_[a-f0-9]{18}$/', $c['database'])) throw new RuntimeException('Invalid isolated database identity');
        $isolated = new PDO('mysql:unix_socket='.$c['socket'].';dbname='.$c['database'].';charset=utf8mb4', $c['username'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $source = $db->getPdo();
        $session = $source->query('SELECT @@session.time_zone AS tz')->fetch(PDO::FETCH_ASSOC);
        $isolated->exec('SET SESSION time_zone='.$isolated->quote($session['tz']));
        $fingerprint = static function (PDO $pdo, string $database) use ($work): array {
            $tables = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
            $result = [];
            foreach ($tables as [$table, $type]) {
                $quoted = '`'.str_replace('`', '``', $table).'`';
                $create = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
                $create = str_replace('`'.$database.'`.', '`ISOLATED`.', $create);
                $schema = hash('sha256', $create);
                $columns = $pdo->query('SHOW FULL COLUMNS FROM '.$quoted)->fetchAll(PDO::FETCH_ASSOC);
                foreach ($columns as &$column) unset($column['Privileges']);
                unset($column);
                $tmp = tempnam($work, 'rows-'); $sorted = $tmp.'.sorted';
                $out = fopen($tmp, 'wb'); $count = 0;
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                $query = $pdo->query('SELECT * FROM '.$quoted);
                while (($row=$query->fetch(PDO::FETCH_NUM)) !== false) {
                    $row = array_map(static fn($v) => $v === null ? null : (string)$v, $row);
                    fwrite($out, hash('sha256', serialize($row))."\n"); $count++;
                }
                $query->closeCursor(); fclose($out);
                $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                $process = proc_open(['sort', $tmp], [0=>['file','/dev/null','r'], 1=>['file',$sorted,'w'], 2=>['file',$work.'/sort-private.log','a']], $pipes, null, ['LC_ALL'=>'C']);
                if (!is_resource($process) || proc_close($process) !== 0) throw new RuntimeException('Row reconciliation sort failed');
                $result[$table] = ['type'=>$type, 'schema'=>$schema, 'columns'=>$columns, 'count'=>$count, 'rows'=>hash_file('sha256',$sorted)];
                unlink($tmp); unlink($sorted);
            }
            ksort($result); return $result;
        };
        $before = $fingerprint($source, 'rajeh_toyjoy');
        $restored = $fingerprint($isolated, $c['database']);
        if ($before !== $restored) throw new RuntimeException('Database restore reconciliation mismatch');
        // Fidelity above is independent of baseline inventory validity. No stock writes.
        $quantityValue = "SELECT COUNT(*) FROM stock_balances b LEFT JOIN (SELECT product_id,store_id,SUM(quantity) qty,SUM(total_cost) value FROM stock_movements GROUP BY product_id,store_id) m ON m.product_id=b.product_id AND m.store_id=b.store_id WHERE b.on_hand<>COALESCE(m.qty,0) OR b.total_value<>COALESCE(m.value,0)";
        $missing = "SELECT COUNT(*) FROM (SELECT DISTINCT product_id,store_id FROM stock_movements) m LEFT JOIN stock_balances b ON b.product_id=m.product_id AND b.store_id=m.store_id WHERE b.id IS NULL";
        $average = "SELECT id,product_id,store_id,on_hand,total_value,average_cost FROM stock_balances WHERE average_cost<>CASE WHEN on_hand=0 THEN 0 ELSE TRUNCATE(total_value/on_hand,4) END ORDER BY id";
        $stockState = static function (PDO $pdo) use ($quantityValue, $missing, $average): array {
            return [
                'quantity_value_mismatches'=>(int)$pdo->query($quantityValue)->fetchColumn(),
                'missing_balances'=>(int)$pdo->query($missing)->fetchColumn(),
                'average_cost_mismatches'=>count($pdo->query($average)->fetchAll(PDO::FETCH_ASSOC)),
            ];
        };
        $sourceStock=$stockState($source); $restoredStock=$stockState($isolated);
        if ($sourceStock!==$restoredStock) throw new RuntimeException('Restored inventory state differs from the source backup');
        file_put_contents($work.'/stock-reconciliation.json', json_encode([
            'database'=>$c['database'], 'restore_fidelity'=>'passed', 'source_state_preserved'=>$sourceStock,
        ], JSON_THROW_ON_ERROR));
        echo "HOTFIX47_INVENTORY=PRESERVED source_and_restore_match\n";
        file_put_contents($work.'/database-reconciliation.json', json_encode($before, JSON_THROW_ON_ERROR));
        echo "HOTFIX43_RESTORE=PASS database_rows_schema_and_all_selected_files\n";
        exit;
    }
    throw new RuntimeException('Unknown backup helper mode');
} catch (Throwable $e) {
    // No framework exception messages: they can contain SQL and credential material.
    $detail = get_class($e) === RuntimeException::class ? $e->getMessage() : 'exception='.get_class($e).' line='.$e->getLine();
    fwrite(STDERR, "HOTFIX43_BACKUP=FAILED detail=".$detail." mode=".($mode ?? 'unknown')." private_log=".($work ?? 'unavailable')."/failure-private.log\n");
    if (isset($work) && is_dir($work)) file_put_contents($work.'/failure-private.log', (string)$e, FILE_APPEND);
    exit(1);
}
