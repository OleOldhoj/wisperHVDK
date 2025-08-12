<?php
// REM Rename call recording files by mapping extension numbers to contact names

/**
 * Rename a recording file by replacing the extension number with the contact name.
 *
 * @param string $filePath     Path to the recording file.
 * @param string $contactsCsv  CSV file containing contact data.
 * @param bool   $debug        Whether to print debug information.
 */
function rename_recording(string $filePath, string $contactsCsv = __DIR__ . '/../contacts.csv', bool $debug = false): string
{
    if ($debug) {
        echo "Debug: starting rename for $filePath\n";
    }
    if (!file_exists($filePath)) {
        if ($debug) {
            echo "Debug: file not found\n";
        }
        return 'Error: file not found';
    }
    if (!file_exists($contactsCsv)) {
        if ($debug) {
            echo "Debug: contacts file not found\n";
        }
        return 'Error: contacts file not found';
    }
    $pattern = '/exten-(\d+)-/';
    if (!preg_match($pattern, $filePath, $matches)) {
        if ($debug) {
            echo "Debug: extension not present in path\n";
        }
        return 'Error: extension not found in path';
    }
    $extension = $matches[1];
    if ($debug) {
        echo "Debug: extracted extension $extension\n";
    }
    $handle = fopen($contactsCsv, 'r');
    if ($handle === false) {
        if ($debug) {
            echo "Debug: cannot open contacts file\n";
        }
        return 'Error: cannot open contacts file';
    }
    $name = null;
    // Skip header
    fgetcsv($handle, 0, ',', '"', '\\');
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if ($debug && isset($row[2])) {
            echo "Debug: checking contact {$row[0]} ({$row[2]})\n";
        }
        if (isset($row[2]) && $row[2] === $extension) {
            $name = str_replace(' ', '', $row[0]);
            if ($debug) {
                echo "Debug: matched extension to $name\n";
            }
            break;
        }
    }
    fclose($handle);
    if ($name === null) {
        if ($debug) {
            echo "Debug: extension $extension not found in contacts\n";
        }
        return 'Error: extension not found';
    }
    $newPath = preg_replace($pattern, 'exten-' . $name . '-', $filePath);
    if ($debug) {
        echo "Debug: renaming to $newPath\n";
    }
    if (!@rename($filePath, $newPath)) {
        if ($debug) {
            echo "Debug: rename failed\n";
        }
        return 'Error: rename failed';
    }
    if ($debug) {
        echo "Debug: rename successful\n";
    }
    return $newPath;
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $path = $argv[1] ?? '';
    if ($path === '') {
        echo 'Error: missing path';
    } else {
        echo rename_recording($path, __DIR__ . '/../contacts.csv', true);
    }
}
?>
