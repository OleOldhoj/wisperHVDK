<?php
require __DIR__ . '/convertThis.php';

function stub_transcribe(string $path): string
{
    return 'Transcribed text';
}

$dir = __DIR__ . '/../sound/07/01';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$wav = $dir . '/exten-TestUser-unknown-20250701-080009-1751349609.81224.wav';
file_put_contents($wav, '');

// Error for missing file
$result = convert_recording($wav . '.missing', 'stub_transcribe');
if ($result !== 'Error: file not found') {
    fwrite(STDERR, "Unexpected error handling: $result\n");
    unlink($wav);
    exit(1);
}

// Success with file URI
$uri = 'file:///' . str_replace('\\', '/', $wav);
$outPath = convert_recording($uri, 'stub_transcribe');
if ($outPath !== preg_replace('/\.wav$/', '.openai.txt', $wav) || !file_exists($outPath) || trim(file_get_contents($outPath)) !== 'Transcribed text') {
    fwrite(STDERR, "Unexpected output\n");
    unlink($wav);
    if (file_exists($outPath)) { unlink($outPath); }
    exit(1);
}

unlink($wav);
unlink($outPath);

echo "OK\n";
?>
