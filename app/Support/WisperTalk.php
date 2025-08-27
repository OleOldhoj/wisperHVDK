<?php
// app/Support/WisperTalk.php
// Fetch records without WisperTALK, transcribe audio via OpenAI Whisper API, and update the database.

/**
 * Process rows with missing WisperTALK values.
 *
 * @param PDO $pdo Database connection
 * @param callable $transcribe Function accepting a file path and returning transcript text
 * @return int Number of rows updated
 */
function process_missing_transcriptions(PDO $pdo, callable $transcribe): int
{
    $select = "SELECT id, filepath FROM sales_call_ratings WHERE (WisperTALK IS NULL OR WisperTALK = '') AND Dept = 'Sales' ";
    fwrite(STDERR, "Running query: {$select}\n");
    $rows = $pdo->query($select)->fetchAll(PDO::FETCH_ASSOC);
    fwrite(STDERR, "Found " . count($rows) . " row(s) needing transcription\n");

    $update = $pdo->prepare("UPDATE sales_call_ratings SET WisperTALK = :text WHERE id = :id");
    $count = 0;
    foreach ($rows as $row) {
        $path = (string) $row['filepath'];
        fwrite(STDERR, "Transcribing id {$row['id']} at {$path}\n");
        $text = $transcribe($path);
        if (strpos($text, 'Error:') === 0) {
            fwrite(STDERR, "Failed to transcribe {$path}: {$text}\n");
            continue;
        }
        fwrite(STDERR, "Updating id {$row['id']} with " . strlen($text) . " chars\n");
        $update->execute([
            ':text' => $text,
            ':id' => (int) $row['id'],
        ]);
        $count++;
    }
    fwrite(STDERR, "Processed {$count} record(s)\n");
    return $count;
}
