<?php
// REM Transcribe a single audio recording and save the result to a .openai.txt file
require __DIR__ . '/../public_html/openai_transcribe.php';

/**
 * Convert a recording to text using a transcription function.
 *
 * @param string   $uri          File path or file:// URI.
 * @param callable $transcribeFn Function that accepts the file path and returns the text.
 * @param bool     $debug        Whether to print debug output.
 *
 * @return string                Output path of the transcript or an error message.
 */
function convert_recording(string $uri, $transcribeFn = 'openai_transcribe', bool $debug = false): string
{
    if ($debug) {
        echo "Debug: input $uri\n";
    }
    $path = $uri;
    if (strpos($uri, 'file://') === 0) {
        $path = substr($uri, 7);
        if (PHP_OS_FAMILY === 'Windows' && isset($path[0]) && $path[0] === '/') {
            $path = ltrim($path, '/');
        }
        if ($debug) {
            echo "Debug: parsed file URI to $path\n";
        }
    }
    if (!file_exists($path)) {
        if ($debug) {
            echo "Debug: file not found\n";
        }
        return 'Error: file not found';
    }
    $text = $transcribeFn($path);
    if (strpos($text, 'Error:') === 0) {
        if ($debug) {
            echo "Debug: transcription failed\n";
        }
        return $text;
    }
    $dir = dirname($path);
    $base = pathinfo($path, PATHINFO_FILENAME);
    $timestamp = date('Ymd-His');
    $id = sprintf('%.5f', microtime(true));
    $parts = explode('-', $base);
    if (count($parts) >= 5) {
        $parts[count($parts) - 2] = $timestamp;
        $parts[count($parts) - 1] = $id;
        $base = implode('-', $parts);
    } else {
        $base .= '-' . $timestamp . '-' . $id;
    }
    $outPath = $dir . DIRECTORY_SEPARATOR . $base . '.openai.txt';
    if ($debug) {
        echo "Debug: writing transcript to $outPath\n";
    }
    file_put_contents($outPath, $text);
    return $outPath;
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    $uri = $argv[1] ?? '';
    if ($uri === '') {
        fwrite(STDERR, "Usage: php convertThis.php <file_uri>\n");
        exit(1);
    }
    echo convert_recording($uri, 'openai_transcribe', true);
}
?>
