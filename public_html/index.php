<?php
// REM Main entry point for transcribing audio files or directories
$config = require __DIR__ . '/../config_files/config.php';

/**
 * Transcribe the given path using the Python helper script.
 */
function transcribe_path(string $path): string
{
    global $config;
    if (!file_exists($path)) {
        return 'Error: path not found';
    }
    $python = escapeshellcmd($config['python_path']);
    $script = escapeshellarg($config['script_path']);
    $target = escapeshellarg($path);
    $command = "$python $script $target";
    $output = shell_exec($command . ' 2>&1');
    return trim((string) $output);
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if (php_sapi_name() === 'cli') {
        $path = $argv[1] ?? $config['sound_dir'];
        echo transcribe_path($path);
    } else {
        $path = $_GET['path'] ?? '';
        if ($path === '') {
            echo 'Error: missing path parameter';
            exit;
        }
        echo transcribe_path($path);
    }
}
?>
