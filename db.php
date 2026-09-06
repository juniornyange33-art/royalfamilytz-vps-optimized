<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $config = require __DIR__ . '/config.php';
    $database = $config['database'] ?? [];
    $driver = (string)($database['driver'] ?? 'mysql');

    if ($driver !== 'mysql') {
        throw new RuntimeException('The application requires MySQL/MariaDB. Set DB_DRIVER to mysql in config.php or local-config.php.');
    }

    $host = (string)($database['host'] ?? '127.0.0.1');
    $port = (string)($database['port'] ?? '3306');
    $name = (string)($database['name'] ?? 'royalfamilytz');
    $user = (string)($database['user'] ?? 'root');
    $pass = (string)($database['password'] ?? '');
    $charset = (string)($database['charset'] ?? 'utf8mb4');

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset={$charset}",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        throw new RuntimeException(
            "MySQL connection failed for database '{$name}' at {$host}:{$port}. " .
            'Start MySQL in XAMPP, confirm the database name, and check DB_USER/DB_PASS in local-config.php.',
            0,
            $e
        );
    }

    return $pdo;
}

function db_available(): bool
{
    try { db(); return true; } catch (Throwable $e) { return false; }
}
?>
