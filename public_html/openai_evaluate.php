<?php
// REM Evaluate sales call transcripts using the OpenAI Responses API

/**
 * Build the OpenAI Responses API payload for a transcript using a fixed assistant.
 *
 * @param string $transcript  Full transcript to analyse
 * @param string $assistantId Assistant identifier
 * @return array<string,mixed> Payload ready for JSON encoding
 */
function openai_build_payload(string $transcript, string $assistantId): array
{
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
        ],
    ];

    $prompt = 'Assess the following sales call transcript. Rate each category on a '
        . 'scale of 1-5 and provide brief notes for WhatWorked, WhatDidNotWork, '
        . 'and a manager_comment. Transcript: ' . $transcript;

    return [
        'assistant_id' => $assistantId,
        // REM Responses API expects an array of messages
        'input' => [
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ],
        // REM Enforce structured JSON output via response_format
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'sales_call_evaluation',
                'schema' => $schema,
                'strict' => true,
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
 * Send transcript text to OpenAI and return structured evaluation data.
 *
 * @param string      $transcript  Full transcript to analyse
 * @param string|null $assistantId Assistant identifier (defaults to env or built-in)
 * @return array<string,mixed> Associative array of evaluation fields or ['error'=>string]
 */
function openai_evaluate(string $transcript, ?string $assistantId = null): array
{
    $apiKey = getenv('OPENAI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        return ['error' => 'missing API key'];
    }

    $assistantId = $assistantId ?: getenv('OPENAI_ASSISTANT_ID') ?: 'asst_dxSC2TjWn45PX7JDdM8RpiyQ';
    $payload = openai_build_payload($transcript, $assistantId);

    fwrite(STDERR, "Preparing OpenAI request (" . strlen($transcript) . " chars)\n");
    fwrite(STDERR, "Request payload: " . json_encode($payload) . "\n");

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        fwrite(STDERR, "cURL error: {$error}\n");
        curl_close($ch);
        return ['error' => $error];
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    fwrite(STDERR, "HTTP status: {$status}\n");
    fwrite(STDERR, "Raw response: {$response}\n");

    $json = json_decode($response, true);
    if ($status !== 200 || !is_array($json)) {
        fwrite(STDERR, "API request failed with status {$status}\n");
        return ['error' => 'API request failed'];
    }

    $text = openai_extract_output_text($json);
    if (!is_string($text) || $text === '') {
        fwrite(STDERR, "No output text in response\n");
        return ['error' => 'No output text in response'];
    }

    $data = json_decode($text, true);
    if (!is_array($data)) {
        fwrite(STDERR, "Invalid JSON in response: {$text}\n");
        return ['error' => 'Invalid JSON in response'];
    }

    return $data;
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if ($argc < 2 || $argc > 3) {
        fwrite(STDERR, "Usage: php openai_evaluate.php <transcript_file> [assistant_id]\n");
        exit(1);
    }
    $transcript = file_get_contents($argv[1]);
    if ($transcript === false) {
        fwrite(STDERR, "Failed to read transcript file\n");
        exit(1);
    }
    $assistant = $argv[2] ?? null;
    $result = openai_evaluate($transcript, $assistant);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
