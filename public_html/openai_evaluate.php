<?php
// openai_evaluate.php - CLI wrapper for transcript evaluation

require_once __DIR__ . '/../app/Support/OpenAiEvaluate.php';

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if ($argc < 2 || $argc > 3) {
        fwrite(STDERR, "Usage: php openai_evaluate.php <transcript_file> [assistant_id]\n");
        exit(1);
    }
    $transcript = file_get_contents($argv[1]);
    if ($transcript === false) {
        fwrite(STDERR, "Failed to read transcript file\n");
        exit(1);
    }
    $assistant = $argv[2] ?? null;
    $result = openai_evaluate($transcript, $assistant);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
