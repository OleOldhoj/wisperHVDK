<?php
// REM Transcribe audio files using the OpenAI Whisper API
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
    if ($status !== 200 || !is_array($json) || !isset($json['text'])) {
        return 'Error: API request failed';
    }
    return (string) $json['text'];
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if ($argc !== 2) {
        fwrite(STDERR, "Usage: php openai_transcribe.php <audio_file>\n");
        exit(1);
    }
    echo openai_transcribe($argv[1]);
}
?>
