<?php
require_once __DIR__ . '/../../public_html/openai_evaluate.php';

putenv('OPENAI_MODEL=test-model');
$payload = openai_build_payload('sample transcript', 'asst_test');

$headers = openai_build_headers('key123');
if (!in_array('OpenAI-Beta: assistants=v2', $headers, true)) {
    fwrite(STDERR, "Missing OpenAI-Beta header\n");
    exit(1);
}

if (($payload['assistant_id'] ?? '') !== 'asst_test') {
    fwrite(STDERR, "Assistant ID not set correctly\n");
    exit(1);
}

if (($payload['model'] ?? '') !== 'test-model') {
    fwrite(STDERR, "Model not set correctly\n");
    exit(1);
}

$jsonSchema = $payload['text']['format']['json_schema'] ?? null;
if (($payload['text']['format']['type'] ?? '') !== 'json_schema' || ($jsonSchema['name'] ?? '') !== 'sales_call_evaluation') {
    fwrite(STDERR, "Missing or incorrect format name\n");
    exit(1);
}

if (!is_array($payload['input']) || ($payload['input'][0]['role'] ?? '') !== 'user') {
    fwrite(STDERR, "Input must be an array of user messages\n");
    exit(1);
}

$schema = $jsonSchema['schema'] ?? null;
if (!is_array($schema) || !isset($schema['properties']['greeting_quality'])) {
    fwrite(STDERR, "Schema not structured as expected\n");
    exit(1);
}

if (!isset($schema['properties']['warning_comment']) || !in_array('warning_comment', $schema['required'] ?? [], true)) {
    fwrite(STDERR, "warning_comment missing from schema\n");
    exit(1);
}

if (($schema['additionalProperties'] ?? null) !== false) {
    fwrite(STDERR, "additionalProperties must be false\n");
    exit(1);
}

$responseText = [
    'output' => [
        [
            'type' => 'message',
            'content' => [
                ['type' => 'output_text', 'text' => '{"foo":1}'],
            ],
        ],
    ],
];

$text = openai_extract_output_text($responseText);
if ($text !== '{"foo":1}') {
    fwrite(STDERR, "Failed to extract output_text\n");
    exit(1);
}

$responseJson = [
    'output' => [
        [
            'type' => 'message',
            'content' => [
                ['type' => 'json', 'json' => ['bar' => 2]],
            ],
        ],
    ],
];

$text = openai_extract_output_text($responseJson);
if ($text !== '{"bar":2}') {
    fwrite(STDERR, "Failed to extract JSON content\n");
    exit(1);
}

$responseTool = [
    'output' => [
        [
            'type' => 'tool',
            'arguments' => ['baz' => 3],
        ],
    ],
];

$text = openai_extract_output_text($responseTool);
if ($text !== '{"baz":3}') {
    fwrite(STDERR, "Failed to extract tool arguments\n");
    exit(1);
}
