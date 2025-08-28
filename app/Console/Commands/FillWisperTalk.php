<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FillWisperTalk extends Command
{
    protected $signature   = 'fill:wispertalk';
    protected $description = 'Transcribe audio files and populate WisperTALK for Sales department';

    public function handle(): int
    {
        // Read config, never env() here
        $apiKey = (string) config('openai.api_key', '');
        $model  = (string) config('openai.audio_model', 'gpt-4o-transcribe');

        $this->info('Starting fill:wispertalk command');

        if ($apiKey === '') {
            $this->error('Missing OPENAI_API_KEY, set it in .env and run: php artisan config:clear && php artisan config:cache');
            return 1;
        }

        $this->info('Model: ' . $model);
        $this->info('Querying database for missing transcriptions');

        $rows = DB::table('sales_call_ratings')
            ->where(function ($q) {
                $q->whereNull('WisperTALK')->orWhere('WisperTALK', '');
            })
            ->where('length_sec', '>', 40)
            ->where('Dept', 'Sales')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $this->info('Found ' . $rows->count() . ' row(s) to process');

        foreach ($rows as $row) {
            $path = (string) $row->filepath;

            $this->line(str_repeat('-', 40));
            $this->info("Processing id {$row->id}");
            $this->line("File path: {$path}");

            if (!is_file($path)) {
                $this->warn("File not found, {$path}");
                continue;
            }

            $this->line('File exists, size: ' . filesize($path) . ' bytes');
            $this->line('Transcribing via OpenAI...');

            // Ask for SRT to get timestamps, fall back if model does not support it
            $resp = $this->openaiTranscribe($apiKey, $path, $model, 'srt');
            if (str_starts_with($resp, 'Error:')) {
                $this->warn("Transcribe failed for {$path}, {$resp}");
                continue;
            }

            $this->line('Raw response length: ' . strlen($resp));

            // If response looks like SRT, parse, else use as is
            $isSrt = $this->looksLikeSrt($resp);
            $this->line('Looks like SRT: ' . ($isSrt ? 'yes' : 'no'));
            $text = $isSrt ? $this->parseSrtToLines($resp) : (string) $resp;

            $this->line('Transcription preview: ' . substr($text, 0, 80));
            $this->line('Updating database...');

            DB::table('sales_call_ratings')
                ->where('id', $row->id)
                ->update([
                    'WisperTALK' => $text,
                ]);

            $this->info("OK, id {$row->id}");
        }

        $this->info('Completed fill:wispertalk command');

        return 0;
    }

    private function formatTimestamp(float $seconds): string
    {
        return gmdate('H:i:s', (int) $seconds);
    }

    /**
     * Simple SRT detector
     */
    private function looksLikeSrt(string $srt): bool
    {
        // Typical SRT has a timecode line "00:00:01,000 --> 00:00:04,000"
        return (bool) preg_match('/\d{2}:\d{2}:\d{2},\d{3}\s+-->\s+\d{2}:\d{2}:\d{2},\d{3}/', $srt);
    }

    /**
     * Parse SRT into "[HH:MM:SS] text" lines using the start time only.
     * Keeps CRLF endings for Mail clients that prefer it.
     */
    private function parseSrtToLines(string $srt): string
    {
        $out   = [];
        $blocks = preg_split('/\R\R+/', trim($srt));

        foreach ($blocks as $block) {
            $lines = preg_split('/\R/', trim($block));
            if (count($lines) < 2) {
                continue;
            }

            // Lines typically: [index], [timecode], [text...]
            // Find timecode line
            $timeLine = null;
            foreach ($lines as $line) {
                if (preg_match('/^(\d{2}:\d{2}:\d{2}),\d{3}\s+-->\s+(\d{2}:\d{2}:\d{2}),\d{3}$/', trim($line), $m)) {
                    $timeLine = $m;
                    break;
                }
            }
            if (!$timeLine) {
                continue;
            }

            // Collect text lines after the timecode line
            $textStart = false;
            $textParts = [];
            foreach ($lines as $line) {
                if ($textStart) {
                    $textParts[] = trim($line);
                }
                if (preg_match('/-->/',$line)) {
                    $textStart = true;
                }
            }
            if (!$textParts) {
                continue;
            }

            $text = trim(preg_replace('/\s+/', ' ', implode(' ', $textParts)));
            $start = $timeLine[1]; // HH:MM:SS from the start
            $out[] = "[{$start}] {$text}";
        }

        return implode("\r\n", $out);
    }

    /**
     * Transcribe audio using OpenAI Audio API.
     * Tries requested format first, then falls back to 'text' if unsupported.
     * @param string $apiKey
     * @param string $audioPath
     * @param string $model
     * @param string $responseFormat  srt, vtt, json, or text
     */
    private function openaiTranscribe(string $apiKey, string $audioPath, string $model, string $responseFormat = 'srt'): string
    {
        if (!file_exists($audioPath)) {
            return 'Error: audio file not found';
        }

        fwrite(STDERR, "openaiTranscribe: path={$audioPath} model={$model} format={$responseFormat}\n");

        // Ensure chunk helper functions are loaded
        require_once base_path('public_html/openai_transcribe.php');
        fwrite(STDERR, "openaiTranscribe: helper loaded\n");

        $transcriber = function (string $path) use ($apiKey, $audioPath, $model, $responseFormat) {
            fwrite(STDERR, "openaiTranscribe: calling OpenAI for chunk {$path}\n");
            $resp = $this->callOpenAI($apiKey, $path, $model, $responseFormat);
            if ($resp['ok']) {
                fwrite(STDERR, "openaiTranscribe: received OK response\n");
                return (string) $resp['body'];
            }

            // If model rejects the format, fall back to 'text'
            $bodyLower = strtolower($resp['body']);
            if ($resp['status'] === 400 && str_contains($bodyLower, 'response_format') && str_contains($bodyLower, 'unsupported')) {
                fwrite(STDERR, "openaiTranscribe: format unsupported, retrying with text\n");
                $fallback = $this->callOpenAI($apiKey, $path, $model, 'text');
                if ($fallback['ok']) {
                    return (string) $fallback['body'];
                }
                return 'Error: HTTP ' . $fallback['status'] . ', ' . substr($fallback['body'], 0, 2000);
            }

            fwrite(STDERR, "openaiTranscribe: error HTTP {$resp['status']}\n");
            return 'Error: HTTP ' . $resp['status'] . ', ' . substr($resp['body'], 0, 2000);
        };

        return \transcribe_with_chunks($audioPath, $transcriber);
    }

    private function callOpenAI(string $apiKey, string $audioPath, string $model, string $responseFormat): array
    {
        fwrite(STDERR, "callOpenAI: sending {$audioPath} with format {$responseFormat}\n");

        $cfile = curl_file_create(
            $audioPath,
            $this->detectMime($audioPath),
            basename($audioPath)
        );

        $post = [
            'model'           => 'whisper-1',
            'file'            => $cfile,
            'response_format' => $responseFormat,
            // 'language'      => 'da', // set if you know it is Danish
            // 'temperature'   => '0',
        ];

        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
            CURLOPT_POSTFIELDS     => $post,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);
            fwrite(STDERR, "callOpenAI: cURL error {$err}\n");
            return ['ok' => false, 'status' => 0, 'body' => 'cURL error, ' . $err];
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        fwrite(STDERR, "callOpenAI: HTTP {$status}\n");
        return ['ok' => $status === 200, 'status' => $status, 'body' => (string) $body];
    }

    private function detectMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'wav'  => 'audio/wav',
            'mp3'  => 'audio/mpeg',
            'm4a'  => 'audio/mp4',
            'flac' => 'audio/flac',
            'ogg'  => 'audio/ogg',
            default => 'application/octet-stream',
        };
    }
}


