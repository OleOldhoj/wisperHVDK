<?php
require_once __DIR__ . '/../fill_wispertalk.php';

$database = getenv('DB_DATABASE') ?: ':memory:';
$dsn = 'sqlite:' . $database;
$pdo = new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS sales_call_ratings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filepath TEXT,
    WisperTALK TEXT
)");
$pdo->exec("INSERT INTO sales_call_ratings (filepath) VALUES ('dummy.wav')");

process_missing_transcriptions($pdo, function (string $path): string {
    return 'stub text';
});

$text = $pdo->query('SELECT WisperTALK FROM sales_call_ratings')->fetchColumn();
if ($text !== 'stub text') {
    fwrite(STDERR, "Test failed\n");
    exit(1);
}
