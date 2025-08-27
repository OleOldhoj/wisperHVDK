<?php
// app/Support/OpenAiEvaluate.php
// Evaluate sales call transcripts using the OpenAI Responses API

// REM Load the native assistant helper, allowing tests to override the path
if (!defined('OA_LIB_PATH')) {
    require_once __DIR__ . '/../../public_html/openai_assistant.php';
} else {
    require_once OA_LIB_PATH;
}

/**
 * Build the OpenAI Responses API payload for a transcript using a fixed assistant.
 *
 * @param string      $transcript  Full transcript to analyse
 * @param string      $assistantId Assistant identifier
 * @param string|null $model       Model name to use (defaults to env or gpt-4.1-mini)
 * @return array<string,mixed> Payload ready for JSON encoding
 */
function openai_build_payload(string $transcript, string $assistantId, ?string $model = null): array
{
    $model = $model ?: getenv('OPENAI_MODEL') ?: 'gpt-4o';
    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'greeting_quality' => ['type' => 'integer'],
            'needs_assessment' => ['type' => 'integer'],
            'product_knowledge' => ['type' => 'integer'],
            'persuasion' => ['type' => 'integer'],
            'closing' => ['type' => 'integer'],
            'WhatWorked' => ['type' => 'string'],
            'WhatDidNotWork' => ['type' => 'string'],
            'manager_comment' => ['type' => 'string'],
            'warning_comment' => ['type' => 'string'],
        ],
        'required' => [
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

    $prompt = 'Assess the following sales call transcript. Rate each category on a '
        . 'scale of 1-5 and provide brief notes for WhatWorked, WhatDidNotWork, '
        . 'and both a manager_comment and a warning_comment. Transcript: ' . $transcript;

    return [
        'assistant_id' => $assistantId,
        'model' => $model,
        // REM Responses API expects an array of messages
        'input' => [
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        // REM Enforce structured JSON output via text.format
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'sales_call_evaluation',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ],
        'max_output_tokens' => 500,
    ];
}

/**
 * Extract the assistant text from an OpenAI Responses API payload.
 *
 * The API may return the generated text either in the convenience field
 * `output_text` or nested within the `output` array. This helper searches both
 * locations and returns the first match.
 *
 * @param array<string,mixed> $json Decoded API response
 * @return string|null The output text or `null` if absent
 */
function openai_extract_output_text(array $json): ?string
{
    if (isset($json['output_text']) && is_string($json['output_text'])) {
        return $json['output_text'];
    }

    if (isset($json['output']) && is_array($json['output'])) {
        foreach ($json['output'] as $item) {
            $type = $item['type'] ?? '';
            if ($type === 'message') {
                foreach ($item['content'] ?? [] as $content) {
                    $ctype = $content['type'] ?? '';
                    if ($ctype === 'output_text' && is_string($content['text'] ?? null)) {
                        return $content['text'];
                    }
                    if (in_array($ctype, ['json', 'json_schema'], true) && isset($content['json'])) {
                        return json_encode($content['json']);
                    }
                    if ($ctype === 'tool_call' && isset($content['arguments'])) {
                        return is_array($content['arguments'])
                            ? json_encode($content['arguments'])
                            : (string) $content['arguments'];
                    }
                }
            } elseif ($type === 'tool' && isset($item['output'])) {
                return is_array($item['output']) ? json_encode($item['output']) : (string) $item['output'];
            } elseif ($type === 'tool' && isset($item['arguments'])) {
                return is_array($item['arguments']) ? json_encode($item['arguments']) : (string) $item['arguments'];
            } elseif ($type === 'output_text' && is_string($item['text'] ?? null)) {
                // Some responses may embed text directly at the top level
                return $item['text'];
            }
        }
    }

    return null;
}

/**
 * Build HTTP headers for OpenAI API requests.
 *
 * @param string $apiKey Secret token for Authorization header
 * @return array<int,string> Array of HTTP header lines
 */
function openai_build_headers(string $apiKey): array
{
    return [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
        'OpenAI-Beta: assistants=v2',
    ];
}

/**
 * Send transcript text to OpenAI and return structured evaluation data.
 *
 * @param string      $transcript  Full transcript to analyse
 * @param string|null $assistantId Assistant identifier (defaults to env or built-in)
 *
 * The model is determined via the OPENAI_MODEL environment variable or defaults
 * to `gpt-4.1-mini`.
 * @return array<string,mixed> Associative array of evaluation fields or ['error'=>string]
 */
function openai_evaluate(string $transcript, ?string $assistantId = null): array
{
    try {
        $apiKey = getenv('OPENAI_API_KEY');
        if ($apiKey === false || $apiKey === '') {
            return ['error' => 'missing API key'];
        }

        $model = getenv('OPENAI_MODEL') ?: 'gpt-4o';

        // REM Initialise context and create assistant if not provided
        $ctx = oa_init_ctx($apiKey, $assistantId);
        oa_debug('Initialised context');
        $instructions = 'Assess the following sales call transcript. Rate each category on a '
            . 'scale of 1-5 and provide brief notes for WhatWorked, WhatDidNotWork, '
            . 'and both a manager_comment and a warning_comment. Reply strictly in JSON with keys '
            . 'greeting_quality, needs_assessment, product_knowledge, persuasion, '
            . 'closing, WhatWorked, WhatDidNotWork, manager_comment and warning_comment.';

        if (empty($assistantId)) {
            $assistantId = oa_create_assistant($ctx, 'Sales Call Evaluator', $instructions, [], $model);
            oa_debug('Created assistant ' . $assistantId);
        } else {
            $ctx['assistant_id'] = $assistantId;
            oa_debug('Using assistant ' . $assistantId);
        }

        $threadId = oa_create_thread($ctx, $transcript, 'user');
        oa_debug('Created thread ' . $threadId);
        $runId    = oa_run_thread($ctx, $threadId);
        oa_debug('Run ID ' . $runId);
        if (!$runId) {
            return ['error' => 'run failed'];
        }

        $messages = oa_list_thread_messages($ctx, $threadId);
        oa_debug('Retrieved ' . count($messages) . ' message(s)');
        $text = null;
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'assistant') {
                foreach ($msg['content'] ?? [] as $content) {
                    $candidate = $content['text']['value'] ?? ($content['text'] ?? null);
                    if (is_string($candidate) && $candidate !== '') {
                        $text = $candidate;
                        break 2;
                    }
                }
            }
        }

        if (!is_string($text) || $text === '') {
            return ['error' => 'No output text in response'];
        }

        $data = json_decode($text, true);
        if (!is_array($data)) {
            return ['error' => 'Invalid JSON in response'];
        }

        oa_debug('Evaluation successful');
        return $data;
    } catch (Throwable $e) {
        fwrite(STDERR, 'OpenAI evaluation failed: ' . $e->getMessage() . "\n");
        return ['error' => $e->getMessage()];
    }
}
