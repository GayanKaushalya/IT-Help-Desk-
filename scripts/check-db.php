<?php

/**
 * Connectivity probe for the SQL Server instance backing IT Helpdesk.
 *
 * Diagnoses the connection without booting Laravel, which is useful when
 * artisan itself cannot start. Credentials are read from backend/.env only.
 *
 * Run with: php scripts/check-db.php
 */

require __DIR__.'/env.php';

$env = read_backend_env(__DIR__.'/../backend/.env');

foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    if (($env[$key] ?? '') === '') {
        fwrite(STDERR, "Missing {$key} in backend/.env\n");
        exit(1);
    }
}

$host = $env['DB_HOST'];
$port = $env['DB_PORT'] ?? '1433';
$database = $env['DB_DATABASE'];

echo 'PDO drivers: '.implode(', ', PDO::getAvailableDrivers()).PHP_EOL;
echo "Target: {$host}:{$port}/{$database} as {$env['DB_USERNAME']}".PHP_EOL.PHP_EOL;

$dsn = sprintf(
    'sqlsrv:Server=%s,%s;Database=%s;Encrypt=%s;TrustServerCertificate=%s;LoginTimeout=15',
    $host,
    $port,
    $database,
    $env['DB_ENCRYPT'] ?? 'no',
    ($env['DB_TRUST_SERVER_CERTIFICATE'] ?? 'true') === 'true' ? 'yes' : 'no',
);

try {
    $pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $row = $pdo->query("
        SELECT
            SUSER_NAME()                     AS login_name,
            DB_NAME()                        AS current_database,
            SERVERPROPERTY('ProductVersion') AS product_version,
            SERVERPROPERTY('Edition')        AS edition
    ")->fetch(PDO::FETCH_ASSOC);

    echo 'CONNECTED'.PHP_EOL;

    foreach ($row as $key => $value) {
        echo str_pad($key, 20).': '.$value.PHP_EOL;
    }

    $tables = $pdo->query('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = \'BASE TABLE\'')
        ->fetchColumn();

    echo str_pad('tables', 20).': '.$tables.PHP_EOL;
    exit(0);
} catch (PDOException $e) {
    fwrite(STDERR, 'FAILED: '.$e->getMessage().PHP_EOL);
    exit(1);
}
