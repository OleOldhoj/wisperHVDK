<?php
define('OA_LIB_PATH', __DIR__ . '/stub_openai_assistant.php');
require_once __DIR__ . '/../../public_html/openai_evaluate.php';

putenv('OPENAI_API_KEY=dummy');

$result = openai_evaluate('sample transcript');
if (($result['greeting_quality'] ?? null) !== 5) {
    fwrite(STDERR, "Unexpected evaluation result\n");
    exit(1);
}

if (($result['warning_comment'] ?? null) !== 'caution') {
    fwrite(STDERR, "warning_comment not returned\n");
    exit(1);
}

global $__stub_calls;
$create = array_filter($__stub_calls, fn($c) => $c[0] === 'oa_create_assistant');
if (count($create) !== 1) {
    fwrite(STDERR, "Assistant creation not invoked\n");
    exit(1);
}
