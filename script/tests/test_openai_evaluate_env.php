<?php
define('OA_LIB_PATH', __DIR__ . '/stub_openai_assistant.php');
require_once __DIR__ . '/../../app/Support/OpenAiEvaluate.php';

putenv('OPENAI_API_KEY=dummy');
putenv('OPENAI_ASSISTANT_ID=from-env');

$result = openai_evaluate('sample transcript');
if (($result['greeting_quality'] ?? null) !== 5) {
    fwrite(STDERR, "Unexpected evaluation result\n");
    exit(1);
}

global $__stub_calls;
$create = array_filter($__stub_calls, fn($c) => $c[0] === 'oa_create_assistant');
if (count($create) !== 0) {
    fwrite(STDERR, "Assistant should not be created when OPENAI_ASSISTANT_ID is set\n");
    exit(1);
}

$init = array_values(array_filter($__stub_calls, fn($c) => $c[0] === 'oa_init_ctx'));
if (empty($init) || ($init[0][1][1] ?? null) !== 'from-env') {
    fwrite(STDERR, "Assistant ID not passed to oa_init_ctx\n");
    exit(1);
}
