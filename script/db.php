<?php
// db.php
// Create a PDO connection using environment variables.

/**
 * Create a PDO connection using environment variables.
 *
 * @return PDO Database connection
 */
function create_pdo_from_env(): PDO
{
    $connection = getenv('DB_CONNECTION') ?: 'mysql';
    $database   = getenv('DB_DATABASE')   ?: 'salescallsanalyse';
    $username   = getenv('DB_USERNAME')   ?: 'root';
    $password   = getenv('DB_PASSWORD')   ?: '';
    $host       = getenv('DB_HOST')       ?: '127.0.0.1';

    if ($connection === 'sqlite') {
        $dsn = 'sqlite:' . $database;
        fwrite(STDERR, "Connecting using SQLite DSN {$dsn}\n");
        return new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
    fwrite(STDERR, "Connecting using MySQL DSN {$dsn}\n");
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}
?>
