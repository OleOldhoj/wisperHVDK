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

echo "OK\n";
?>
