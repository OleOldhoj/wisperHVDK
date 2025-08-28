<?php
// script/evaluate_wispertalk.php
// Select WisperTALK transcripts and evaluate them via OpenAI assistant.

require_once __DIR__ . '/fill_call_ratings.php';

/**
 * Evaluate existing WisperTALK transcripts and print assistant responses.
 *
 * @param PDO      $pdo      Database connection
 * @param callable $evaluate Function accepting transcript text and returning evaluation
 * @param int      $limit    Maximum number of rows to process
 * @return int Number of rows processed
 */
function evaluate_wispertalk(PDO $pdo, callable $evaluate, int $limit = 10): int
{
    $stmt = $pdo->prepare(
        'SELECT id, WisperTALK FROM sales_call_ratings '
        . 'WHERE WisperTALK IS NOT NULL AND WisperTALK <> "" '
        . 'ORDER BY id LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($rows as $row) {
        $talk = (string) $row['WisperTALK'];
        fwrite(STDERR, "Evaluating id {$row['id']} (" . strlen($talk) . " chars)\n");
        $result = $evaluate($talk);
        echo json_encode(['id' => $row['id'], 'result' => $result], JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $count++;
    }

    return $count;
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    try {
        $pdo = create_pdo_from_env();
    } catch (PDOException $e) {
        fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . "\n");
        exit(1);
    }

    $processed = evaluate_wispertalk($pdo, 'openai_evaluate');
    fwrite(STDOUT, "Processed {$processed} record(s)\n");
}
