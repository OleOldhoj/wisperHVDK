<?php
// REM Rename call recording files by mapping extension numbers to contact names

/**
 * Load all contacts from the CSV file into an array keyed by extension.
 */
function load_contacts(string $contactsCsv, bool $debug = false): array
{
    if (!file_exists($contactsCsv)) {
        if ($debug) {
            echo "Debug: contacts file not found\n";
        }
        return [];
    }
    $handle = fopen($contactsCsv, 'r');
    if ($handle === false) {
        if ($debug) {
            echo "Debug: cannot open contacts file\n";
        }
        return [];
    }
    $contacts = [];
    // Skip header
    fgetcsv($handle, 0, ',', '"', '\\');
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if (isset($row[2])) {
            $name = str_replace(' ', '', $row[0]);
            $department = isset($row[1]) ? str_replace(' ', '', $row[1]) : '';
            if ($department !== '') {
                $name .= '-' . $department;
            }
            $contacts[$row[2]] = $name;
            if ($debug) {
                echo "Debug: loaded contact {$row[0]} ({$row[2]})\n";
            }
        }
    }
    fclose($handle);
    return $contacts;
}

/**
 * Rename a recording file by replacing the extension number with the contact name.
 *
 * @param string $filePath Path to the recording file.
 * @param array  $contacts Mapping of extension numbers to contact names.
 * @param bool   $debug    Whether to print debug information.
 */
function rename_recording(string $filePath, array $contacts, bool $debug = false): string
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
    $filename = basename($filePath);
    $pattern = '/^(out-[^-]+-|exten-)(\d+)(-.*)$/';
    if (!preg_match($pattern, $filename, $matches)) {
        if ($debug) {
            echo "Debug: extension not present in filename\n";
        }
        return 'Error: extension not found in path';
    }
    $extension = $matches[2];
    if ($debug) {
        echo "Debug: extracted extension $extension\n";
    }
    if (!isset($contacts[$extension])) {
        if ($debug) {
            echo "Debug: extension $extension not found in contacts\n";
        }
        return 'Error: extension not found';
    }
    $name = $contacts[$extension];
    if ($debug) {
        echo "Debug: matched extension to $name\n";
    }
    $newFilename = $matches[1] . $name . $matches[3];
    $newPath = dirname($filePath) . DIRECTORY_SEPARATOR . $newFilename;
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

/**
 * Rename all recordings under a directory by replacing extension numbers with contact names.
 *
 * @param string $baseDir     Directory to scan for recordings.
 * @param string $contactsCsv CSV file containing contact data.
 * @param bool   $debug       Whether to print debug information.
 *
 * @return array<string, string> Mapping of original file paths to new paths or error messages.
 */
function rename_recordings(string $baseDir, string $contactsCsv = __DIR__ . '/../contacts.csv', bool $debug = false): array
{
    if ($debug) {
        echo "Debug: scanning directory $baseDir\n";
    }
    if (!is_dir($baseDir)) {
        if ($debug) {
            echo "Debug: directory not found\n";
        }
        return [];
    }
    $contacts = load_contacts($contactsCsv, $debug);
    $results = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (preg_match('/exten-\d+-/', $path) || preg_match('/^out-[^-]+-\d+-/', $file->getFilename())) {
            $results[$path] = rename_recording($path, $contacts, $debug);
        }
    }
    return $results;
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $path = $argv[1] ?? "C:\wisper\sound";
    if ($path === '') {
    } elseif (is_dir($path)) {
        $results = rename_recordings($path, __DIR__ . '/../contacts.csv', true);
        foreach ($results as $old => $new) {
            echo "$old => $new\n";
        }
    } else {
        $contacts = load_contacts(__DIR__ . '/../contacts.csv', true);
        echo rename_recording($path, $contacts, true);
    }
}
?>
