<?php
require __DIR__ . '/rename_recording.php';

$base = __DIR__ . '/../sound';
$dir1 = $base . '/07/02';
$dir2 = $base . '/07/03';
if (!is_dir($dir1)) { mkdir($dir1, 0777, true); }
if (!is_dir($dir2)) { mkdir($dir2, 0777, true); }

$old1 = $dir1 . '/exten-1183-unknown-20250701-111155-1751361115.85474';
$new1 = $dir1 . '/exten-SuneKidmose-unknown-20250701-111155-1751361115.85474';
$old2 = $dir2 . '/exten-1183-unknown-20250701-111155-1751361115.85475';
$new2 = $dir2 . '/exten-SuneKidmose-unknown-20250701-111155-1751361115.85475';
file_put_contents($old1, '');
file_put_contents($old2, '');

$results = rename_recordings($base);
if ($results[$old1] !== $new1 || !file_exists($new1) || $results[$old2] !== $new2 || !file_exists($new2)) {
    fwrite(STDERR, "Unexpected output\n");
    if (file_exists($old1)) { unlink($old1); }
    if (file_exists($new1)) { unlink($new1); }
    if (file_exists($old2)) { unlink($old2); }
    if (file_exists($new2)) { unlink($new2); }
    exit(1);
}

unlink($new1);
unlink($new2);

// Test with debug enabled and capture output
file_put_contents($old1, '');
file_put_contents($old2, '');
ob_start();
$results = rename_recordings($base, __DIR__ . '/../contacts.csv', true);
$debug = ob_get_clean();
if ($results[$old1] !== $new1 || !file_exists($new1) || $results[$old2] !== $new2 || !file_exists($new2) || strpos($debug, 'Debug:') === false) {
    fwrite(STDERR, "Unexpected debug output\n");
    if (file_exists($old1)) { unlink($old1); }
    if (file_exists($new1)) { unlink($new1); }
    if (file_exists($old2)) { unlink($old2); }
    if (file_exists($new2)) { unlink($new2); }
    exit(1);
}

unlink($new1);
unlink($new2);

echo "OK\n";
?>
