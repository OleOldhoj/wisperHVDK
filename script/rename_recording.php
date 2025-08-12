<?php
// REM Rename call recording files by mapping extension numbers to contact names

/**
 * Rename a recording file by replacing the extension number with the contact name.
 */
function rename_recording(string $filePath, string $contactsCsv = __DIR__ . '/../contacts.csv'): string
{
    if (!file_exists($filePath)) {
        return 'Error: file not found';
    }
    if (!file_exists($contactsCsv)) {
        return 'Error: contacts file not found';
    }
    $pattern = '/exten-(\d+)-/';
    if (!preg_match($pattern, $filePath, $matches)) {
        return 'Error: extension not found in path';
    }
    $extension = $matches[1];
    $handle = fopen($contactsCsv, 'r');
    if ($handle === false) {
        return 'Error: cannot open contacts file';
    }
    $name = null;
    // Skip header
    fgetcsv($handle, 0, ',', '"', '\\');
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if (isset($row[2]) && $row[2] === $extension) {
            $name = str_replace(' ', '', $row[0]);
            break;
        }
    }
    fclose($handle);
    if ($name === null) {
        return 'Error: extension not found';
    }
    $newPath = preg_replace($pattern, 'exten-' . $name . '-', $filePath);
    if (!@rename($filePath, $newPath)) {
        return 'Error: rename failed';
    }
    return $newPath;
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $path = $argv[1] ?? '';
    if ($path === '') {
        echo 'Error: missing path';
    } else {
        echo rename_recording($path);
    }
}
?>
