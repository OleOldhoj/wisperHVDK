<?php
// 1234_cron_fill_call_ratings.php
// Cron entry point to update rating fields from WisperTALK transcripts.

require_once __DIR__ . '/fill_call_ratings.php';

try {
    $pdo = create_pdo_from_env();
} catch (PDOException $e) {
    fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$updated = process_missing_ratings($pdo, 'openai_evaluate');

// Log in ISO 8601 format so cron captures when it ran.
fwrite(STDOUT, date('c') . " Updated {$updated} record(s)\n");

