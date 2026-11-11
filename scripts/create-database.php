<?php

/**
 * Creates the IT Helpdesk database on the shared SQL Server instance.
 *
 * Laravel migrations create the tables, but the database itself has to exist
 * before artisan can connect, so this runs against `master` first.
 *
 * Run with: php scripts/create-database.php
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
$username = $env['DB_USERNAME'];
$password = $env['DB_PASSWORD'];

if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $database)) {
    fwrite(STDERR, "Refusing to create database with unsafe name: {$database}\n");
    exit(1);
}

$dsn = sprintf(
    'sqlsrv:Server=%s,%s;Database=master;Encrypt=no;TrustServerCertificate=yes;LoginTimeout=15',
    $host,
    $port
);

try {
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $exists = $pdo->prepare('SELECT database_id FROM sys.databases WHERE name = ?');
    $exists->execute([$database]);

    if ($exists->fetchColumn()) {
        echo "Database [{$database}] already exists." . PHP_EOL;
    } else {
        // CREATE DATABASE cannot be parameterised, hence the name validation above.
        $pdo->exec("CREATE DATABASE [{$database}]");
        echo "Database [{$database}] created." . PHP_EOL;
    }

    $pdo->exec("USE [{$database}]");
    $info = $pdo->query('SELECT DB_NAME() AS name, collation_name FROM sys.databases WHERE name = DB_NAME()')
        ->fetch(PDO::FETCH_ASSOC);

    echo "Collation: {$info['collation_name']}" . PHP_EOL;
    exit(0);
} catch (PDOException $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
