<?php
require __DIR__ . '/../public_html/index.php';
$result = transcribe_path('nonexistent_path');
if ($result !== 'Error: path not found') {
    fwrite(STDERR, "Unexpected output: $result\n");
    exit(1);
}
echo "OK\n";
?>
