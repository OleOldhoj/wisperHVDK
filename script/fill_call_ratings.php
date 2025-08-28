<?php
// fill_call_ratings.php
// Analyse WisperTALK transcripts and populate rating fields in sales_call_ratings.

require_once __DIR__ . '/../app/Support/OpenAiEvaluate.php';
require_once __DIR__ . '/db.php';

// REM Use predefined OpenAI assistant only when OPENAI_ASSISTANT_ID is set
$assistantId = getenv('OPENAI_ASSISTANT_ID') ?: null;

/**
 * Process rows missing rating information.
 *
 * @param PDO $pdo Database connection
 * @param callable $evaluate Function accepting transcript text and returning rating data
 * @return int Number of rows updated
 */
function process_missing_ratings(PDO $pdo, callable $evaluate): int
{
    $select = "SELECT id, WisperTALK FROM sales_call_ratings "
        . "WHERE greeting_quality = 0 OR needs_assessment = 0 OR "
        . "product_knowledge = 0 OR persuasion = 0 OR closing = 0 OR "
        . "WhatWorked = '' OR WhatDidNotWork = '' OR manager_comment IS NULL OR warning_comment IS NULL";
    fwrite(STDERR, "Running query: {$select}\n");
    $rows = $pdo->query($select)->fetchAll(PDO::FETCH_ASSOC);
    fwrite(STDERR, "Found " . count($rows) . " row(s) needing evaluation\n");

    $update = $pdo->prepare(
        "UPDATE sales_call_ratings SET "
        . "greeting_quality = :greeting_quality, "
        . "needs_assessment = :needs_assessment, "
        . "product_knowledge = :product_knowledge, "
        . "persuasion = :persuasion, "
        . "closing = :closing, "
        . "WhatWorked = :WhatWorked, "
        . "WhatDidNotWork = :WhatDidNotWork, "
        . "manager_comment = :manager_comment, "
        . "warning_comment = :warning_comment "
        . "WHERE id = :id"
    );

    $count = 0;
    foreach ($rows as $row) {
        $talk = (string) $row['WisperTALK'];
        fwrite(STDERR, "Evaluating id {$row['id']} (" . strlen($talk) . " chars)\n");
        $result = $evaluate($talk);
        fwrite(STDERR, "Result for id {$row['id']}: " . json_encode($result) . "\n");
        if (!is_array($result) || isset($result['error'])) {
            fwrite(STDERR, "Failed to evaluate id {$row['id']}: " . ($result['error'] ?? 'unknown') . "\n");
            continue;
        }
        fwrite(STDERR, "Updating id {$row['id']}\n");
        $update->execute([
            ':greeting_quality' => (int) ($result['greeting_quality'] ?? 0),
            ':needs_assessment' => (int) ($result['needs_assessment'] ?? 0),
            ':product_knowledge' => (int) ($result['product_knowledge'] ?? 0),
            ':persuasion' => (int) ($result['persuasion'] ?? 0),
            ':closing' => (int) ($result['closing'] ?? 0),
            ':WhatWorked' => (string) ($result['WhatWorked'] ?? ''),
            ':WhatDidNotWork' => (string) ($result['WhatDidNotWork'] ?? ''),
            ':manager_comment' => $result['manager_comment'] ?? null,
            ':warning_comment' => $result['warning_comment'] ?? null,
            ':id' => (int) $row['id'],
        ]);
        $count++;
    }
    fwrite(STDERR, "Processed {$count} record(s)\n");
    return $count;
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    try {
        $pdo = create_pdo_from_env();
        fwrite(STDERR, "Database connection established\n");
    } catch (PDOException $e) {
        fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . "\n");
        exit(1);
    }

    $updated = process_missing_ratings($pdo, fn(string $talk): array => openai_evaluate($talk, $assistantId));
    fwrite(STDOUT, "Updated {$updated} record(s)\n");
}
?>
