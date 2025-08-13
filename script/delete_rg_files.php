<?php
declare(strict_types=1);

/**
 * Delete WAV files starting with "rg-".
 *
 * Usage: php delete_rg_files.php [directory]
 */

// Determine directory
$directory = $argv[1] ?? __DIR__ . '/../sound';
$directory = rtrim($directory, DIRECTORY_SEPARATOR);

if (!is_dir($directory)) {
    fwrite(STDERR, "Directory not found: {$directory}" . PHP_EOL);
    exit(1);
}

echo "Scanning for rg-*.wav files in: {$directory}" . PHP_EOL;

// Use RecursiveDirectoryIterator for any depth
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
);

$count = 0;
foreach ($iterator as $file) {
    if (strtolower($file->getExtension()) === 'wav'
        && stripos($file->getFilename(), 'rg-') === 0) {

        $filePath = $file->getPathname();

        // Uncomment to actually delete
        unlink($filePath);

        echo 'Deleted .. ' .$filePath  . $file->getFilename() . PHP_EOL;
        $count++;
    }
}

echo "Total deleted: {$count}" . PHP_EOL;
