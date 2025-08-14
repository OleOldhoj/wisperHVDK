<?php
require_once __DIR__ . '/../fill_call_ratings.php';

$database = getenv('DB_DATABASE') ?: ':memory:';
$dsn = 'sqlite:' . $database;
$pdo = new PDO($dsn);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS sales_call_ratings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    WisperTALK TEXT,
    greeting_quality INTEGER NOT NULL DEFAULT 0,
    needs_assessment INTEGER NOT NULL DEFAULT 0,
    product_knowledge INTEGER NOT NULL DEFAULT 0,
    persuasion INTEGER NOT NULL DEFAULT 0,
    closing INTEGER NOT NULL DEFAULT 0,
    WhatWorked TEXT NOT NULL DEFAULT '',
    WhatDidNotWork TEXT NOT NULL DEFAULT '',
    manager_comment TEXT,
    warning_comment TEXT
)");
$pdo->exec("INSERT INTO sales_call_ratings (WisperTALK) VALUES ('example talk')");

process_missing_ratings($pdo, function (string $talk): array {
    return [
        'greeting_quality' => 3,
        'needs_assessment' => 4,
        'product_knowledge' => 5,
        'persuasion' => 2,
        'closing' => 1,
        'WhatWorked' => 'good knowledge',
        'WhatDidNotWork' => 'weak closing',
        'manager_comment' => 'improve closing',
        'warning_comment' => 'check closing',
    ];
});

$row = $pdo->query('SELECT greeting_quality, needs_assessment, product_knowledge, persuasion, closing, WhatWorked, WhatDidNotWork, manager_comment, warning_comment FROM sales_call_ratings')->fetch(PDO::FETCH_ASSOC);
if ($row['greeting_quality'] != 3 || $row['needs_assessment'] != 4 || $row['product_knowledge'] != 5 || $row['persuasion'] != 2 || $row['closing'] != 1 || $row['WhatWorked'] !== 'good knowledge' || $row['WhatDidNotWork'] !== 'weak closing' || $row['manager_comment'] !== 'improve closing' || $row['warning_comment'] !== 'check closing') {
    fwrite(STDERR, "Test failed\n");
    exit(1);
}
