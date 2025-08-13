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

    // Channels
    fseek($handle, 22);
    $channels = unpack('v', fread($handle, 2))[1] ?? 0;

    // Sample rate
    $sampleRate = unpack('V', fread($handle, 4))[1] ?? 0;

    // Bits per sample
    fseek($handle, 34);
    $bitsPerSample = unpack('v', fread($handle, 2))[1] ?? 0;

    // Data size
    fseek($handle, 40);
    $dataSize = unpack('V', fread($handle, 4))[1] ?? 0;

    fclose($handle);

    if ($channels === 0 || $bitsPerSample === 0 || $sampleRate === 0) {
        return null;
    }

    $bytesPerSample = ($bitsPerSample / 8) * $channels;
    return $dataSize / ($sampleRate * $bytesPerSample);
}

// Determine directory
$directory = $argv[1] ?? __DIR__ . '/../sound';
$directory = rtrim($directory, DIRECTORY_SEPARATOR);

if (!is_dir($directory)) {
    fwrite(STDERR, "Directory not found: {$directory}" . PHP_EOL);
    exit(1);
}

echo "Scanning: {$directory}" . PHP_EOL;

// Use RecursiveDirectoryIterator for any depth
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (strtolower($file->getExtension()) === 'wav') {
        $filePath = $file->getPathname();
        $duration = getWavDuration($filePath);

        if ($duration !== null && $duration < 60) {
            // Uncomment to actually delete
            unlink($filePath);
            echo 'Deleted .. '.$filePath . $file->getFilename() . ' (' . round($duration, 2) . ' sec)' . PHP_EOL;
        }
    }
}

echo "Done." . PHP_EOL;
