[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
$database = 'toyjoy_master_migration_20260820'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$targetMigration = '2026_08_10_000044_create_gift_receipts_cards_returns.php'

if (-not (Test-Path -LiteralPath $mysql)) {
    throw "MariaDB client was not found at $mysql."
}

function Invoke-Mysql([string] $query, [string] $schema = '') {
    $arguments = @('--protocol=TCP', '--host=127.0.0.1', '--port=3307', '--user=root', '--batch', '--skip-column-names')

    if ($schema) {
        $arguments += "--database=$schema"
    }

    $arguments += "--execute=$query"
    $output = & $mysql @arguments 2>&1

    if ($LASTEXITCODE -ne 0) {
        throw ($output -join [Environment]::NewLine)
    }

    return $output
}

function Invoke-Artisan([string[]] $arguments) {
    $output = & php artisan @arguments 2>&1

    if ($LASTEXITCODE -ne 0) {
        throw ($output -join [Environment]::NewLine)
    }

    return $output
}

$existingDatabase = Invoke-Mysql "SELECT schema_name FROM information_schema.schemata WHERE schema_name = '$database'"

if ($existingDatabase) {
    $existingTables = Invoke-Mysql "SELECT table_name FROM information_schema.tables WHERE table_schema = '$database'" $database

    if ($existingTables) {
        throw "Refusing to reset non-empty database '$database'. Inspect and explicitly remove only known disposable test data before rerunning this verification."
    }
}
else {
    Invoke-Mysql "CREATE DATABASE ``$database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" | Out-Null
}

$previousEnvironment = @{}
foreach ($name in 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD') {
    $previousEnvironment[$name] = [Environment]::GetEnvironmentVariable($name, 'Process')
}

try {
    $env:DB_CONNECTION = 'mysql'
    $env:DB_HOST = '127.0.0.1'
    $env:DB_PORT = '3307'
    $env:DB_DATABASE = $database
    $env:DB_USERNAME = 'root'
    $env:DB_PASSWORD = ''

    $migrationFiles = Get-ChildItem -Path (Join-Path $projectRoot 'database/migrations') -File -Filter '*.php' |
        Where-Object { $_.Name -le $targetMigration } |
        Sort-Object Name

    foreach ($migration in $migrationFiles) {
        Invoke-Artisan @('migrate', '--force', "--path=database/migrations/$($migration.Name)") | Out-Null
    }

    Invoke-Artisan @('migrate:rollback', '--force', '--step=1') | Out-Null

    Invoke-Artisan @('migrate', '--force', "--path=database/migrations/$targetMigration") | Out-Null

    $giftReceiptForeignKey = Invoke-Mysql @"
SELECT kcu.constraint_name
FROM information_schema.key_column_usage AS kcu
INNER JOIN information_schema.referential_constraints AS rc
    ON rc.constraint_schema = kcu.constraint_schema
    AND rc.table_name = kcu.table_name
    AND rc.constraint_name = kcu.constraint_name
WHERE kcu.constraint_schema = '$database'
    AND kcu.table_name = 'gift_receipts'
    AND kcu.column_name = 'used_return_id'
    AND kcu.referenced_table_schema = '$database'
    AND kcu.referenced_table_name = 'retail_returns'
    AND rc.delete_rule = 'SET NULL'
"@ $database

    if (-not $giftReceiptForeignKey) {
        throw 'Second forward migration did not restore gift_receipts.used_return_id as a SET NULL foreign key to retail_returns.'
    }

    Write-Output 'PASS: fresh forward, target rollback, and second forward succeeded.'
    Write-Output "PASS: gift_receipts.used_return_id references retail_returns with ON DELETE SET NULL ($($giftReceiptForeignKey -join ', '))."
}
finally {
    foreach ($name in $previousEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($name, $previousEnvironment[$name], 'Process')
    }
}
