<?php
define('OA_LIB_PATH', __DIR__ . '/stub_openai_assistant.php');
require_once __DIR__ . '/../evaluate_wispertalk.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE sales_call_ratings (id INTEGER PRIMARY KEY AUTOINCREMENT, WisperTALK TEXT)');
$pdo->exec("INSERT INTO sales_call_ratings (WisperTALK) VALUES ('hello transcript')");

putenv('OPENAI_API_KEY=dummy');
putenv('OPENAI_ASSISTANT_ID=from-env');

ob_start();
$count = evaluate_wispertalk($pdo, 'openai_evaluate');
$output = ob_get_clean();

if ($count !== 1) {
    fwrite(STDERR, "Unexpected count\n");
    exit(1);
}
if (strpos($output, 'greeting_quality') === false) {
    fwrite(STDERR, "Missing evaluation output\n");
    exit(1);
}
