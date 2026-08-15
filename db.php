<?php

/**
 * Singleton database connection (Creational pattern, Lec 10).
 *
 * Ensures a single PDO instance is created per process and reuses it
 * everywhere. Configuration is read from environment variables so tests
 * can point the app at an in-memory SQLite database without code changes.
 */
final class DbConnection
{
    private static ?DbConnection $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $host    = getenv('DB_HOST') ?: '127.0.0.1';
        $db      = getenv('DB_NAME') ?: 'nosh_softdev';
        $user    = getenv('DB_USER') ?: 'root';
        $pass    = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';

        $dsn = getenv('DB_DSN') ?: "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance(): DbConnection
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}

// Backwards-compatible global so existing code (`global $pdo`) keeps working.
$pdo = DbConnection::getInstance()->getPdo();
