<?php
require_once __DIR__ . '/../../public_html/openai_evaluate.php';

$payload = openai_build_payload('sample transcript');

if (($payload['text']['format']['name'] ?? '') !== 'sales_call_evaluation') {
    fwrite(STDERR, "Missing or incorrect format name\n");
    exit(1);
}

$schema = $payload['text']['format']['schema'] ?? null;
if (!is_array($schema) || !isset($schema['properties']['greeting_quality'])) {
    fwrite(STDERR, "Schema not structured as expected\n");
    exit(1);
}

if (($schema['additionalProperties'] ?? null) !== false) {
    fwrite(STDERR, "additionalProperties must be false\n");
    exit(1);
}

$response = [
    'output' => [
        [
            'type' => 'message',
            'content' => [
                ['type' => 'output_text', 'text' => '{"foo":1}'],
            ],
        ],
    ],
];

$text = openai_extract_output_text($response);
if ($text !== '{"foo":1}') {
    fwrite(STDERR, "Failed to extract output text\n");
    exit(1);
}
