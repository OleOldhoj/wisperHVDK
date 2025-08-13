<?php
declare(strict_types=1);

/**
 * Delete WAV audio files shorter than one minute.
 *
 * Usage: php delete_short_files.php [directory]
 */
function getWavDuration(string $file): ?float
{
    $handle = fopen($file, 'rb');
    if ($handle === false) {
        return null;
    }

    fseek($handle, 22);
    $channels = unpack('v', fread($handle, 2))[1];

    $sampleRate = unpack('V', fread($handle, 4))[1];

    fseek($handle, 34);
    $bitsPerSample = unpack('v', fread($handle, 2))[1];

    fseek($handle, 40);
    $dataSize = unpack('V', fread($handle, 4))[1];
    fclose($handle);

    if ($channels === 0 || $bitsPerSample === 0 || $sampleRate === 0) {
        return null;
    }

    $bytesPerSample = ($bitsPerSample / 8) * $channels;
    return $dataSize / ($sampleRate * $bytesPerSample);
}

$directory = $argv[1] ?? __DIR__ . '/../sound';
$directory = rtrim($directory, DIRECTORY_SEPARATOR);

if (!is_dir($directory)) {
    fwrite(STDERR, "Directory not found: {$directory}" . PHP_EOL);
    exit(1);
}

$files = glob($directory . '/*.{wav}', GLOB_BRACE);
foreach ($files as $file) {
    $duration = getWavDuration($file);
    if ($duration !== null && $duration < 60) {
        unlink($file);
        echo 'Deleted ' . basename($file) . PHP_EOL;
    }
}
