<?php
require __DIR__ . '/rename_recording.php';

$dir = __DIR__ . '/../sound/07/02';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$old = $dir . '/exten-1183-unknown-20250701-111155-1751361115.85474';
$new = $dir . '/exten-SuneKidmose-unknown-20250701-111155-1751361115.85474';
file_put_contents($old, '');

$result = rename_recording($old);
if ($result !== $new || !file_exists($new)) {
    fwrite(STDERR, "Unexpected output: $result\n");
    if (file_exists($old)) { unlink($old); }
    if (file_exists($new)) { unlink($new); }
    exit(1);
}

unlink($new);

// Test with debug enabled and capture output
file_put_contents($old, '');
ob_start();
$result = rename_recording($old, __DIR__ . '/../contacts.csv', true);
$debug = ob_get_clean();
if ($result !== $new || !file_exists($new) || strpos($debug, 'Debug:') === false) {
    fwrite(STDERR, "Unexpected debug output: $debug\n");
    if (file_exists($old)) { unlink($old); }
    if (file_exists($new)) { unlink($new); }
    exit(1);
}

unlink($new);

echo "OK\n";
?>
