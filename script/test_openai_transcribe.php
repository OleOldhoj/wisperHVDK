<?php
require __DIR__ . '/../public_html/openai_transcribe.php';

$result = openai_transcribe('missing.wav');
if ($result !== 'Error: audio file not found') {
    fwrite(STDERR, "Unexpected output: $result\n");
    exit(1);
}

$result = openai_transcribe(__FILE__);
if ($result !== 'Error: missing API key') {
    fwrite(STDERR, "Unexpected output: $result\n");
    exit(1);
}

$segments = [
    ['start' => 0.0, 'text' => 'Hello'],
    ['start' => 4.2, 'text' => 'Hi again'],
];
$expected = "[00:00:00] Hello\r\n[00:00:04] Hi again";
if (format_transcript_segments($segments) !== $expected) {
    fwrite(STDERR, "Unexpected formatting\n");
    exit(1);
}

echo "OK\n";
?>
