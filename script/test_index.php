<?php
require __DIR__ . '/../public_html/index.php';
$result = transcribe_file('nonexistent.wav');
if ($result !== 'Error: file not found') {
    fwrite(STDERR, "Unexpected output: $result\n");
    exit(1);
}
echo "OK\n";
?>
