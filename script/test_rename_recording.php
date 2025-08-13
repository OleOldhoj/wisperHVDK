<?php
require __DIR__ . '/rename_recording.php';

$base = __DIR__ . '/../sound';
$dir1 = $base . '/07/02';
$dir2 = $base . '/07/03';
if (!is_dir($dir1)) { mkdir($dir1, 0777, true); }
if (!is_dir($dir2)) { mkdir($dir2, 0777, true); }

$old1 = $dir1 . '/out-25787816-1183-20250701-111155-1751361115.85474.wav';
$new1 = $dir1 . '/out-25787816-SuneKidmose-Salgs-20250701-111155-1751361115.85474.wav';
$old2 = $dir2 . '/out-25787816-1183-20250701-111155-1751361115.85475.wav';
$new2 = $dir2 . '/out-25787816-SuneKidmose-Salgs-20250701-111155-1751361115.85475.wav';
$old3 = $dir1 . '/exten-8504-unknown-20250701-111155-1751361115.85476.wav';
$new3 = $dir1 . '/exten-MortenHyldgaard-CS-unknown-20250701-111155-1751361115.85476.wav';
file_put_contents($old1, '');
file_put_contents($old2, '');
file_put_contents($old3, '');

$results = rename_recordings($base);
if ($results[$old1] !== $new1 || !file_exists($new1) || $results[$old2] !== $new2 || !file_exists($new2) || $results[$old3] !== $new3 || !file_exists($new3)) {
    fwrite(STDERR, "Unexpected output\n");
    if (file_exists($old1)) { unlink($old1); }
    if (file_exists($new1)) { unlink($new1); }
    if (file_exists($old2)) { unlink($old2); }
    if (file_exists($new2)) { unlink($new2); }
    if (file_exists($old3)) { unlink($old3); }
    if (file_exists($new3)) { unlink($new3); }
    exit(1);
}

unlink($new1);
unlink($new2);
unlink($new3);

// Test with debug enabled and capture output
file_put_contents($old1, '');
file_put_contents($old2, '');
file_put_contents($old3, '');
ob_start();
$results = rename_recordings($base, __DIR__ . '/../contacts.csv', true);
$debug = ob_get_clean();
if ($results[$old1] !== $new1 || !file_exists($new1) || $results[$old2] !== $new2 || !file_exists($new2) || $results[$old3] !== $new3 || !file_exists($new3) || strpos($debug, 'Debug:') === false) {
    fwrite(STDERR, "Unexpected debug output\n");
    if (file_exists($old1)) { unlink($old1); }
    if (file_exists($new1)) { unlink($new1); }
    if (file_exists($old2)) { unlink($old2); }
    if (file_exists($new2)) { unlink($new2); }
    if (file_exists($old3)) { unlink($old3); }
    if (file_exists($new3)) { unlink($new3); }
    exit(1);
}

unlink($new1);
unlink($new2);
unlink($new3);

echo "OK\n";
?>
