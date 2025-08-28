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
 * Transcribe a potentially large audio file by chunking when necessary.
 *
 * The file is first passed through {@see prepare_audio_for_openai} to ensure
 * it is encoded as a 64 kbps mono MP3 when large. If the prepared file still
 * exceeds the OpenAI content limit, it is split into multiple segments using
 * ffmpeg's segmenter. Each chunk is then passed to the supplied callback which
 * performs the actual transcription. The resulting texts are concatenated with
 * CRLF line endings.
 *
 * @param callable $transcribeFn function(string $path): string
 */
function transcribe_with_chunks(string $audioPath, callable $transcribeFn): string
{
    [$prepared, $mime, $tmp] = prepare_audio_for_openai($audioPath);

    $maxSize = (int) getenv('OPENAI_MAX_CONTENT');
    if ($maxSize <= 0) {
        $maxSize = 26214400; // 25 MB default limit
    }

    $maxSeconds = (int) getenv('OPENAI_MAX_DURATION');
    if ($maxSeconds <= 0) {
        $maxSeconds = 900; // 15 minutes default limit
    }

    $size    = filesize($prepared);
    $duration = $size !== false ? (int) ceil($size / 8000) : 0; // ~8000 bytes/sec

    if (($size !== false && $size > $maxSize) || $duration > $maxSeconds) {
        $seconds = min($maxSeconds, max(1, (int) floor($maxSize / 8000)));
        $pattern = tempnam(sys_get_temp_dir(), 'wisper_chunk');
        if ($pattern === false) {
            if ($tmp && file_exists($tmp)) {
                @unlink($tmp);
            }
            return 'Error: failed to create temp file';
        }
        $pattern .= '%03d.' . pathinfo($prepared, PATHINFO_EXTENSION);
        $ffmpeg  = getenv('FFMPEG_BIN') ?: 'ffmpeg';
        $cmd     = sprintf(
            '%s -y -i %s -f segment -segment_time %d -c copy %s 2>&1',
            escapeshellcmd($ffmpeg),
            escapeshellarg($prepared),
            $seconds,
            escapeshellarg($pattern)
        );
        exec($cmd, $out, $code);
        if ($code !== 0) {
            if ($tmp && file_exists($tmp)) {
                @unlink($tmp);
            }
            return 'Error: failed to split audio';
        }
        $chunks = glob(str_replace('%03d', '*', $pattern)) ?: [];
        sort($chunks);
        $texts = [];
        foreach ($chunks as $chunk) {
            $text = $transcribeFn($chunk);
            @unlink($chunk);
            if (str_starts_with($text, 'Error:')) {
                if ($tmp && file_exists($tmp)) {
                    @unlink($tmp);
                }
                return $text;
            }
            $texts[] = $text;
        }
        if ($tmp && file_exists($tmp)) {
            @unlink($tmp);
        }
        return implode("\r\n", $texts);
    }

    $text = $transcribeFn($prepared);
    if ($tmp && file_exists($tmp)) {
        @unlink($tmp);
    }
    return $text;
}

/**
 * Transcribe the given audio file using OpenAI's Whisper API.
 */
function openai_transcribe(string $audioPath): string
{
    if (!file_exists($audioPath)) {
        return 'Error: audio file not found';
    }

    $fake = getenv('OPENAI_TRANSCRIBE_FAKE');
    $apiKey = getenv('OPENAI_API_KEY');
    if ($fake === false && ($apiKey === false || $apiKey === '')) {
        return 'Error: missing API key';
    }

    $transcribeFn = function (string $path) use ($fake, $apiKey) {
        if ($fake !== false) {
            return $fake;
        }

        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        $cfile = curl_file_create($path, 'audio/mpeg', basename($path));
        $data = [
            'model' => 'whisper-1',
            'file'  => $cfile,
            'response_format' => 'verbose_json',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
            CURLOPT_POSTFIELDS     => $data,
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return 'Error: ' . $error;
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string) $response, true);
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
    };

    return transcribe_with_chunks($audioPath, $transcribeFn);
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if ($argc !== 2) {
        fwrite(STDERR, "Usage: php openai_transcribe.php <audio_file>\n");
        exit(1);
    }
    echo openai_transcribe($argv[1]);
}
?>
