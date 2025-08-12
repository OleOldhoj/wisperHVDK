<?php
// REM Main entry point for transcribing audio files
$config = require __DIR__ . '/../config_files/config.php';

/**
 * Transcribe an audio file using the Python helper script.
 */
function transcribe_file(string $filePath): string
{
    global $config;
    if (!file_exists($filePath)) {
        return 'Error: file not found';
    }
    $python = escapeshellcmd($config['python_path']);
    $script = escapeshellarg($config['script_path']);
    $file   = escapeshellarg($filePath);
    $command = "$python $script $file";
    $output = shell_exec($command . ' 2>&1');
    return trim((string) $output);
}

if (php_sapi_name() !== 'cli') {
    $file = $_GET['file'] ?? '';
    if ($file === '') {
        echo 'Error: missing file parameter';
        exit;
    }
    echo transcribe_file($file);
}
?>
