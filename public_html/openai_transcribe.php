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
 * Prepare an audio file for upload. If the file exceeds half of the
 * OpenAI content limit (default 25 MB) it is converted to MP3 to reduce
 * size. Returns an array with the path to use, mime type and an optional
 * temporary path that should be cleaned up by the caller.
 *
 * @return array{0:string,1:string,2:?string}
 */
function prepare_audio_for_openai(string $audioPath): array
{
    $maxSize = (int) getenv('OPENAI_MAX_CONTENT');
    if ($maxSize <= 0) {
        $maxSize = 26214400; // 25 MB default limit
    }

    $size = filesize($audioPath);
    if ($size !== false && $size > ($maxSize / 2)) {
        $ffmpeg = getenv('FFMPEG_BIN') ?: 'ffmpeg';
        $tmp    = tempnam(sys_get_temp_dir(), 'wisper');
        if ($tmp !== false) {
            $tmp .= '.mp3';
            $cmd = sprintf(
                '%s -y -i %s -vn -ar 16000 -ac 1 -b:a 64k %s 2>&1',
                escapeshellcmd($ffmpeg),
                escapeshellarg($audioPath),
                escapeshellarg($tmp)
            );
            exec($cmd, $out, $code);
            if ($code === 0 && file_exists($tmp)) {
                return [$tmp, 'audio/mpeg', $tmp];
            }
        }
    }

    return [$audioPath, 'audio/wav', null];
}

/**
 * Transcribe the given audio file using OpenAI's Whisper API.
 */
function openai_transcribe(string $audioPath): string
{
    $fake = getenv('OPENAI_TRANSCRIBE_FAKE');
    if ($fake !== false) {
        return $fake;
    }
    if (!file_exists($audioPath)) {
        return 'Error: audio file not found';
    }
    $apiKey = getenv('OPENAI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        return 'Error: missing API key';
    }
    [$sendPath, $mime, $tmp] = prepare_audio_for_openai($audioPath);
    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    $cfile = curl_file_create($sendPath, $mime, basename($sendPath));
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
    if ($tmp !== null) {
        @unlink($tmp);
    }
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
