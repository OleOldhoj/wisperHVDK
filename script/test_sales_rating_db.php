<?php
declare(strict_types=1);

require __DIR__ . '/init_sales_rating_db.php';

$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'test';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$pdo->exec('DROP TABLE IF EXISTS sales_call_ratings');
initSalesRatingDb($pdo);

$stmt = $pdo->query("SHOW TABLES LIKE 'sales_call_ratings'");
$row = $stmt->fetch(PDO::FETCH_NUM);

if ($row === false) {
    echo "sales_call_ratings table missing\n";
    exit(1);
}

echo "sales_call_ratings table exists\n";
$pdo->exec('DROP TABLE sales_call_ratings');
$pdo = null;
