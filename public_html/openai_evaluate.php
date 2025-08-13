<?php
// REM Evaluate sales call transcripts using the OpenAI Responses API

/**
 * Send transcript text to OpenAI and return structured evaluation data.
 *
 * @param string $transcript Full transcript to analyse
 * @return array<string,mixed> Associative array of evaluation fields or ['error'=>string]
 */
function openai_evaluate(string $transcript): array
{
    $apiKey = getenv('OPENAI_API_KEY');
    if ($apiKey === false || $apiKey === '') {
        return ['error' => 'missing API key'];
    }

    $schema = [
        'name' => 'sales_call_evaluation',
        'schema' => [
            'type' => 'object',
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
        ],
    ];

    $prompt = 'Assess the following sales call transcript. Rate each category on a '
        . 'scale of 1-5 and provide brief notes for WhatWorked, WhatDidNotWork, '
        . 'and a manager_comment. Transcript: ' . $transcript;

    $payload = [
        'model' => 'gpt-5',
        'input' => $prompt,
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => $schema,
        ],
        'max_output_tokens' => 500,
    ];

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
        curl_close($ch);
        return ['error' => $error];
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);
    if ($status !== 200 || !is_array($json)) {
        return ['error' => 'API request failed'];
    }

    $text = $json['output_text'] ?? '';
    $data = json_decode($text, true);
    if (!is_array($data)) {
        return ['error' => 'Invalid JSON in response'];
    }

    return $data;
}

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if ($argc !== 2) {
        fwrite(STDERR, "Usage: php openai_evaluate.php <transcript_file>\n");
        exit(1);
    }
    $transcript = file_get_contents($argv[1]);
    if ($transcript === false) {
        fwrite(STDERR, "Failed to read transcript file\n");
        exit(1);
    }
    $result = openai_evaluate($transcript);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
