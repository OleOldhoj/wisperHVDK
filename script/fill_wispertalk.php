<?php
// fill_wispertalk.php
// Fetch records without WisperTALK, transcribe audio via OpenAI Whisper API, and update the database.

require_once __DIR__ . '/../public_html/openai_transcribe.php';

/**
 * Process rows with missing WisperTALK values.
 *
 * @param PDO $pdo Database connection
 * @param callable $transcribe Function accepting a file path and returning transcript text
 * @return int Number of rows updated
 */
function process_missing_transcriptions(PDO $pdo, callable $transcribe): int
{
    $select = "SELECT id, filepath FROM sales_call_ratings WHERE WisperTALK IS NULL OR WisperTALK = ''";
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

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $connection = getenv('DB_CONNECTION') ?: 'mysql';
    $database   = getenv('DB_DATABASE')   ?: 'salescallsanalyse';
    $username   = getenv('DB_USERNAME')   ?: 'root';
    $password   = getenv('DB_PASSWORD')   ?: '';
    $host       = getenv('DB_HOST')       ?: '127.0.0.1';

    if ($connection === 'sqlite') {
        $dsn = "sqlite:" . $database;
        $username = null;
        $password = null;
        fwrite(STDERR, "Connecting using SQLite DSN {$dsn}\n");
    } else {
        $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
        fwrite(STDERR, "Connecting using MySQL DSN {$dsn}\n");
    }

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        fwrite(STDERR, "Database connection established\n");
    } catch (PDOException $e) {
        fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . "\n");
        exit(1);
    }

    $updated = process_missing_transcriptions($pdo, 'openai_transcribe');
    fwrite(STDOUT, "Updated {$updated} record(s)\n");
}
