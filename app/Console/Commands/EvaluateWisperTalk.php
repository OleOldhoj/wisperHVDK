<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class EvaluateWisperTalk extends Command
{
    protected $signature   = 'EvaluateWisperTalk:cron';
    protected $description = 'Evaluate WisperTALK transcripts via OpenAI and store ratings';

    public function handle(): int
    {
        $apiKey = env('OPENAI_API_KEY');
        $model  = env('gpt-4o');

        if (empty($apiKey)) {
            $this->error('OPENAI_API_KEY is not set in .env');
            return 1;
        }

        // Fetch up to 50 rows that clearly need evaluation
        $rows = DB::table('sales_call_ratings')
            ->select(['id', 'WisperTALK'])
            ->whereRaw('CHAR_LENGTH(WisperTALK) > 100')
            ->whereNull('greeting_quality')
            ->whereNull('needs_assessment')
            ->whereNull('closing')
            ->whereNull('product_knowledge')
            ->limit(1)
            ->get();

        $this->info('Found ' . $rows->count() . ' row(s) to process');

        if ($rows->isEmpty()) {
            return 0;
        }

        $processed = 0;

        foreach ($rows as $row) {
            try {
                $transcript = (string) $row->WisperTALK;
                if ($transcript === '') {
                    $this->warn("Row {$row->id} has empty transcript, skipping");
                    continue;
                }

                $payload = $this->buildResponsesPayload($transcript, $model);
                $this->line(json_encode($payload, JSON_PRETTY_PRINT));

$headers = [
    'Authorization' => 'Bearer ' . trim(env('OPENAI_API_KEY', '')),
    'Content-Type'  => 'application/json',
];
if ($org = trim(env('OPENAI_ORG', ''))) {
    $headers['OpenAI-Organization'] = $org;
}
if ($project = trim(env('OPENAI_PROJECT', ''))) {
    $headers['OpenAI-Project'] = $project;
}

                if (! $resp->ok()) {
                    $this->warn("Row {$row->id} OpenAI error, HTTP {$resp->status()} " . $resp->body());
                    continue;
                }

                $json = $resp->json();
                $data = $this->extractJsonFromResponse($json);
                if (! is_array($data)) {
                    $this->warn("Row {$row->id} returned invalid or empty JSON");
                    continue;
                }

                // Normalize types, never trust the model blindly
                $update = [
                    'greeting_quality'  => (int) ($data['greeting_quality'] ?? 0),
                    'needs_assessment'  => (int) ($data['needs_assessment'] ?? 0),
                    'product_knowledge' => (int) ($data['product_knowledge'] ?? 0),
                    'persuasion'        => (int) ($data['persuasion'] ?? 0),
                    'closing'           => (int) ($data['closing'] ?? 0),
                    'WhatWorked'        => (string) ($data['WhatWorked'] ?? ''),
                    'WhatDidNotWork'    => (string) ($data['WhatDidNotWork'] ?? ''),
                    'manager_comment'   => isset($data['manager_comment']) ? (string) $data['manager_comment'] : null,
                    'warning_comment'   => isset($data['warning_comment']) ? (string) $data['warning_comment'] : null,
                    'updated_at'        => now(),
                ];
                print_R($update);
                DB::table('sales_call_ratings')->where('id', $row->id)->update($update);
                $processed++;

                                     // Be polite to the API
                usleep(4 * 250_000); // 0.25s

            } catch (\Throwable $e) {
                $this->error("Row {$row->id} error, " . $e->getMessage());
                continue;
            }
        }

        $this->info("Processed {$processed} record(s)");
        return 0;
    }

    private function buildResponsesPayload(string $transcript, string $model): array
    {
        $schema = [
            'type'                 => 'object',
            'additionalProperties' => false,
            'properties'           => [
                'greeting_quality'  => ['type' => 'integer'],
                'needs_assessment'  => ['type' => 'integer'],
                'product_knowledge' => ['type' => 'integer'],
                'persuasion'        => ['type' => 'integer'],
                'closing'           => ['type' => 'integer'],
                'WhatWorked'        => ['type' => 'string'],
                'WhatDidNotWork'    => ['type' => 'string'],
                'manager_comment'   => ['type' => 'string'],
                'warning_comment'   => ['type' => 'string'],
            ],
            'required'             => [
                'greeting_quality',
                'needs_assessment',
                'product_knowledge',
                'persuasion',
                'closing',
                'WhatWorked',
                'WhatDidNotWork',
                'manager_comment',
                'warning_comment',
            ],
        ];

        $system = 'You are a strict sales call evaluator. Return valid JSON only, no prose. All ratings are integers from 1 to 5. Write short, actionable comments.';
        $user   = 'Assess the following sales call transcript. Return JSON with exactly these keys, greeting_quality, needs_assessment, product_knowledge, persuasion, closing, WhatWorked, WhatDidNotWork, manager_comment, warning_comment. Transcript, ' . $transcript;

        return [
            'model'             => $model,
            'input'             => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'text'              => [
                'format' => [
                    'type'   => 'json_schema',
                    'name'   => 'sales_call_evaluation',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
            'max_output_tokens' => 500,
        ];
    }

    /**
     * Extract JSON object from Responses API reply.
     */
    private function extractJsonFromResponse(array $json): ?array
    {
        // Preferred convenience field
        if (isset($json['output_text']) && is_string($json['output_text'])) {
            $decoded = json_decode($json['output_text'], true);
            return is_array($decoded) ? $decoded : null;
        }

        // Walk the output array
        if (isset($json['output']) && is_array($json['output'])) {
            foreach ($json['output'] as $item) {
                if (($item['type'] ?? '') === 'message') {
                    foreach ($item['content'] ?? [] as $content) {
                        // JSON content
                        if (isset($content['json']) && is_array($content['json'])) {
                            return $content['json'];
                        }
                        // Text content that is actually JSON
                        if (isset($content['type']) && $content['type'] === 'output_text') {
                            $txt = $content['text'] ?? null;
                            if (is_string($txt)) {
                                $decoded = json_decode($txt, true);
                                if (is_array($decoded)) {
                                    return $decoded;
                                }
                            }
                        }
                    }
                }
            }
        }

        return null;
    }
}
