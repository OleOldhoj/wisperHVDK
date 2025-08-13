<?php
declare(strict_types=1);

/**
 * Initialize the sales call rating table in a MySQL database.
 */
function initSalesRatingDb(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS sales_call_ratings (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            call_id VARCHAR(50) NOT NULL,
            greeting_quality TINYINT UNSIGNED NOT NULL,
            needs_assessment TINYINT UNSIGNED NOT NULL,
            product_knowledge TINYINT UNSIGNED NOT NULL,
            persuasion TINYINT UNSIGNED NOT NULL,
            closing TINYINT UNSIGNED NOT NULL,
            manager_comment TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

if (PHP_SAPI === 'cli' && realpath($argv[0]) === __FILE__) {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'test';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    initSalesRatingDb($pdo);
    echo "sales_call_ratings table initialized in {$db}\n";
}
