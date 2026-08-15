<?php

/**
 * Loads schema.sql into the database described by the standard DB_* env vars.
 *
 * Used by the CI mysql-integration job so the schema can be loaded without a
 * system MySQL client being installed on the runner (no apt dependencies).
 * schema.sql creates the database itself, so no dbname is needed to connect.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

function fail(string $message): never
{
    echo '::error::' . str_replace("\n", '%0A', $message) . "\n";
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function connect(): PDO
{
    try {
        return new PDO(
            'mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1')
            . ';port=' . (getenv('DB_PORT') ?: '3306')
            . ';charset=utf8mb4',
            getenv('DB_USER') ?: 'root',
            getenv('DB_PASS') ?: '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        fail('load_schema: cannot connect to MySQL at ' . (getenv('DB_HOST') ?: '127.0.0.1') . ':' . (getenv('DB_PORT') ?: '3306') . ': ' . $e->getMessage());
    }
}

$pdo = connect();

$sql = file_get_contents(__DIR__ . '/../schema.sql');
if ($sql === false) {
    fail('load_schema: cannot read schema.sql');
}

$loaded = 0;
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
    try {
        $pdo->exec($stmt);
        $loaded++;
    } catch (PDOException $e) {
        fail('load_schema: statement failed: ' . $e->getMessage() . ' (statement: ' . str_replace("\n", ' ', $stmt) . ')');
    }
}
echo "schema loaded ($loaded statements)\n";
