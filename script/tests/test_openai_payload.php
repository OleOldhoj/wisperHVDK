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
