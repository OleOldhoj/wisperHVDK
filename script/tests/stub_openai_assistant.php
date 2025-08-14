<?php
// REM Stub implementation for oa_* functions used in tests
$__stub_calls = [];

function oa_init_ctx(string $api_key, ?string $assistant_id = null, string $base_url = '', string $version_header = ''): array {
    global $__stub_calls;
    $__stub_calls[] = ['oa_init_ctx', func_get_args()];
    return ['api_key' => $api_key, 'assistant_id' => $assistant_id];
}

function oa_create_assistant(array &$ctx, string $name, string $instructions, array $tools, string $model = ''): string {
    global $__stub_calls;
    $__stub_calls[] = ['oa_create_assistant', func_get_args()];
    $ctx['assistant_id'] = 'stub-assistant';
    return 'stub-assistant';
}

function oa_create_thread(array $ctx, string $content, string $role = 'user'): string {
    global $__stub_calls;
    $__stub_calls[] = ['oa_create_thread', func_get_args()];
    return 'stub-thread';
}

function oa_run_thread(array &$ctx, string $thread_id) {
    global $__stub_calls;
    $__stub_calls[] = ['oa_run_thread', func_get_args()];
    return 'stub-run';
}

function oa_list_thread_messages(array $ctx, string $thread_id): array {
    global $__stub_calls;
    $__stub_calls[] = ['oa_list_thread_messages', func_get_args()];
    return [
        [
            'role' => 'assistant',
            'content' => [
                ['text' => ['value' => '{"greeting_quality":5,"needs_assessment":4,"product_knowledge":3,"persuasion":4,"closing":5,"WhatWorked":"good","WhatDidNotWork":"none","manager_comment":"nice"}']]
            ],
        ],
    ];
}
