<?php
// REM Transcribe audio files using the OpenAI Whisper API

/**
 * Format a timestamp in seconds to HH:MM:SS.
 */
function format_timestamp(float $seconds): string
{
    return gmdate('H:i:s', (int) $seconds);
}

/**
 * Build a transcript string from Whisper segments.
 * Each line contains a timestamp and the spoken text using CRLF endings.
 *
 * @param array<int, array<string, mixed>> $segments
 */
function format_transcript_segments(array $segments): string
{
    $lines = [];
    foreach ($segments as $segment) {
        if (!isset($segment['start'], $segment['text'])) {
            continue;
        }
        $time = format_timestamp((float) $segment['start']);
        $lines[] = "[$time] " . trim((string) $segment['text']);
    }
    return implode("\r\n", $lines);
}

/**
 * Transcribe the given audio file using OpenAI's Whisper API.
 */
function openai_transcribe(string $audioPath): string
{
    if (!file_exists($audioPath)) {
        return 'Error: audio file not found';
    }
    $apiKey = getenv('OPENAI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        return 'Error: missing API key';
    }
    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    $cfile = curl_file_create($audioPath, 'audio/wav', basename($audioPath));
    $data = [
        'model' => 'whisper-1',
        'file' => $cfile,
        'response_format' => 'verbose_json',
    ];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => $data,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return 'Error: ' . $error;
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($response, true);
    if ($status !== 200 || !is_array($json)) {
        return 'Error: API request failed';
    }
    if (isset($json['segments']) && is_array($json['segments'])) {
        return format_transcript_segments($json['segments']);
    }
    if (isset($json['text'])) {
        return (string) $json['text'];
    }
    return 'Error: API response missing text';
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if ($argc !== 2) {
        fwrite(STDERR, "Usage: php openai_transcribe.php <audio_file>\n");
        exit(1);
    }
    echo openai_transcribe($argv[1]);
}
?>
