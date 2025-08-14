<?php
/**
 * Native PHP rewrite of OpenAIAssistant
 * Procedural style with a shared $ctx array for state
 */

/** -------- Context and init -------- */

/**
 * Create a new context
 */
function oa_init_ctx(
    string $api_key,
    ?string $assistant_id = null,
    string $base_url = 'https://api.openai.com/v1',
    string $version_header = 'OpenAI-Beta: assistants=v2'
): array {
    return [
        'api_key'        => $api_key,
        'assistant_id'   => $assistant_id,
        'base_url'       => rtrim($base_url, '/'),
        'version_header' => $version_header,
        'has_tool_calls' => false,
        'tool_call_id'   => null,
        'log_file'       => __DIR__ . '/tool_calls_log',
    ];
}

function oa_is_header_valid(array $ctx, string $expectedHeader): bool {
    return $ctx['version_header'] === $expectedHeader;
}

/** -------- Assistants -------- */

function oa_create_assistant(array &$ctx, string $name, string $instructions, array $tools, string $model = 'gpt-4-turbo-preview'): string {
    $resp = oa_send_post_request($ctx, '/assistants', [
        'name'         => $name,
        'instructions' => $instructions,
        'model'        => $model,
        'tools'        => $tools,
    ]);
    if (empty($resp['id'])) {
        throw new Exception('Unable to create an assistant');
    }
    $ctx['assistant_id'] = $resp['id'];
    return $resp['id'];
}

function oa_modify_assistant(array &$ctx, string $name, string $instructions, array $tools): string {
    if (empty($ctx['assistant_id'])) {
        throw new Exception('You need to provide an assistant_id or create an assistant.');
    }
    $resp = oa_send_post_request($ctx, "/assistants/{$ctx['assistant_id']}", [
        'name'         => $name,
        'instructions' => $instructions,
        'model'        => 'gpt-4-turbo-preview',
        'tools'        => $tools,
    ]);
    if (empty($resp['id'])) {
        throw new Exception('Unable to modify the assistant');
    }
    $ctx['assistant_id'] = $resp['id'];
    return $resp['id'];
}

function oa_list_assistants(array $ctx): array {
    $resp = oa_send_get_request($ctx, '/assistants');
    return $resp['data'] ?? [];
}

/** -------- Threads and messages -------- */

function oa_create_thread(array $ctx, string $content, string $role = 'user'): string {
    $resp = oa_send_post_request($ctx, '/threads', [
        'messages' => [
            ['role' => $role, 'content' => $content],
        ],
    ]);
    if (empty($resp['id'])) {
        throw new Exception('Unable to create a thread');
    }
    return $resp['id'];
}

function oa_get_thread(array $ctx, string $thread_id): array {
    $resp = oa_send_get_request($ctx, "/threads/{$thread_id}");
    if (empty($resp['id'])) {
        throw new Exception('Unable to retrieve the thread');
    }
    return $resp;
}

function oa_add_message(array &$ctx, string $thread_id, string $content, string $role = 'user') {
    // Check latest run for requires_action before adding a new message
    $runs = oa_list_runs($ctx, $thread_id);
    if (!empty($runs)) {
        $last = $runs[0];
        if (($last['status'] ?? '') === 'requires_action') {
            $ctx['has_tool_calls'] = true;
            $ctx['tool_call_id'] = $last['id'] ?? null;
            return false;
        }
        $ctx['has_tool_calls'] = false;
        $ctx['tool_call_id'] = null;
    }

    $resp = oa_send_post_request($ctx, "/threads/{$thread_id}/messages", [
        'role'    => $role,
        'content' => $content,
    ]);
    if (empty($resp['id'])) {
        throw new Exception('Unable to create a message');
    }
    return $resp['id'];
}

function oa_get_message(array $ctx, string $thread_id, string $message_id): array {
    $resp = oa_send_get_request($ctx, "/threads/{$thread_id}/messages/{$message_id}");
    if (empty($resp['id'])) {
        throw new Exception('Unable to retrieve the message');
    }
    return $resp;
}

function oa_list_thread_messages(array $ctx, string $thread_id): array {
    $resp = oa_send_get_request($ctx, "/threads/{$thread_id}/messages");
    return $resp['data'] ?? [];
}

/** -------- Runs -------- */

function oa_run_thread(array &$ctx, string $thread_id) {
    // If last run requires action, do not start a new one
    $runs = oa_list_runs($ctx, $thread_id);
    if (!empty($runs)) {
        $last = $runs[0];
        if (($last['status'] ?? '') === 'requires_action') {
            $ctx['has_tool_calls'] = true;
            $ctx['tool_call_id'] = $last['id'] ?? null;
            return false;
        }
        $ctx['has_tool_calls'] = false;
        $ctx['tool_call_id'] = null;
    }

    $run_id = oa_create_run($ctx, $thread_id, $ctx['assistant_id']);
    do {
        sleep(2);
        $run = oa_get_run($ctx, $thread_id, $run_id);
    } while (!in_array(($run['status'] ?? ''), ['completed', 'requires_action'], true));

    if ($run['status'] === 'requires_action') {
        $ctx['has_tool_calls'] = true;
        $ctx['tool_call_id'] = $run['id'] ?? null;
        return $run['id'] ?? false;
    }
    if ($run['status'] === 'completed') {
        return $run['id'] ?? false;
    }
    return false;
}

/**
 * Execute tools described by the run required_action
 * $optional_object is an optional target for callable methods
 */
function oa_execute_tools(array &$ctx, string $thread_id, string $execution_id, $optional_object = null): array {
    $run = oa_get_run($ctx, $thread_id, $execution_id);
    $calls = $run['required_action']['submit_tool_outputs']['tool_calls'] ?? [];
    $outputs = [];
    $log = '';

    foreach ($calls as $call) {
        $method_name = $call['function']['name'] ?? '';
        $method_args = json_decode($call['function']['arguments'] ?? '[]', true);
        $callable = $optional_object ? [$optional_object, $method_name] : $method_name;

        if (is_callable($callable)) {
            // Ensure args are an array list, not associative only
            $args = is_array($method_args) ? array_values($method_args) : [];
            $data = call_user_func_array($callable, $args);

            $outputs[] = [
                'tool_call_id' => $call['id'],
                'output'       => json_encode($data),
            ];
            $log .= "{$method_name} -> " . print_r($method_args, true);
        } else {
            throw new Exception("Failed to execute tool, {$method_name} is not callable");
        }
    }

    oa_write_log($ctx, $log);
    $ctx['has_tool_calls'] = false;
    return $outputs;
}

function oa_submit_tool_outputs(array &$ctx, string $thread_id, string $execution_id, array $outputs) {
    $resp = oa_send_post_request($ctx, "/threads/{$thread_id}/runs/{$execution_id}/submit_tool_outputs", [
        'tool_outputs' => $outputs,
    ]);
    oa_write_log($ctx, "outputs -> " . print_r($outputs, true));

    if (empty($resp['id'])) {
        throw new Exception('Unable to submit tool outputs');
    }

    do {
        // If you want local debugging prints, add them here
        sleep(5);
        $run = oa_get_run($ctx, $thread_id, $resp['id']);
    } while (!in_array(($run['status'] ?? ''), ['completed', 'requires_action'], true));

    if ($run['status'] === 'requires_action') {
        $ctx['has_tool_calls'] = true;
        $ctx['tool_call_id'] = $run['id'] ?? null;
        return $run['id'] ?? false;
    }
    if ($run['status'] === 'completed') {
        return $run['id'] ?? false;
    }
    return false;
}

function oa_get_run(array $ctx, string $thread_id, string $run_id): array {
    $resp = oa_send_get_request($ctx, "/threads/{$thread_id}/runs/{$run_id}");
    if (empty($resp['id'])) {
        throw new Exception('Unable to get the run');
    }
    return $resp;
}

function oa_list_threads(array $ctx): array {
    $resp = oa_send_get_request($ctx, '/threads');
    return $resp['data'] ?? [];
}

function oa_list_runs(array $ctx, string $thread_id): array {
    $resp = oa_send_get_request($ctx, "/threads/{$thread_id}/runs");
    return $resp['data'] ?? [];
}

function oa_create_run(array $ctx, string $thread_id, string $assistant_id): string {
    $resp = oa_send_post_request($ctx, "/threads/{$thread_id}/runs", [
        'assistant_id' => $assistant_id,
    ]);
    if (empty($resp['id'])) {
        throw new Exception('Unable to create a run');
    }
    return $resp['id'];
}

/** -------- HTTP helpers -------- */

function oa_send_get_request(array $ctx, string $route): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ctx['base_url'] . $route);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$ctx['api_key']}",
        'Content-Type: application/json',
        'Accept: application/json',
        $ctx['version_header'],
    ]);
    return oa_execute_request($ch);
}

function oa_send_post_request(array $ctx, string $route, ?array $payload = null): array {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ctx['base_url'] . $route);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    if (!empty($payload)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$ctx['api_key']}",
        'Content-Type: application/json',
        'Accept: application/json',
        $ctx['version_header'],
    ]);
    return oa_execute_request($ch);
}

function oa_execute_request($ch): array {
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($errno = curl_errno($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new Exception('CURL failed to call OpenAI API: ' . $err, $errno);
    }
    curl_close($ch);

    if ($http_code < 200 || $http_code >= 300) {
        // include body to help debugging
        throw new Exception("OpenAI API returned HTTP {$http_code}. " . print_r($response, true));
    }

    $decoded = json_decode($response, true);
    if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to decode JSON response: ' . json_last_error_msg());
    }
    return $decoded;
}

/** -------- Logging -------- */

function oa_write_log(array $ctx, string $message): bool {
    $entry = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
    return (bool) file_put_contents($ctx['log_file'], $entry, FILE_APPEND);
}

/** -------- Example usage --------
$ctx = oa_init_ctx('YOUR_API_KEY');
$assistantId = oa_create_assistant($ctx, 'My Assistant', 'Be helpful', [['type' => 'code_interpreter']]);
$threadId = oa_create_thread($ctx, 'Hello');
oa_add_message($ctx, $threadId, 'Second message');
$runId = oa_run_thread($ctx, $threadId);
// If $ctx['has_tool_calls'] === true, fetch required actions, execute tools, then:
if ($ctx['has_tool_calls'] && $ctx['tool_call_id']) {
    $outputs = oa_execute_tools($ctx, $threadId, $ctx['tool_call_id'], $optional_object = null);
    oa_submit_tool_outputs($ctx, $threadId, $ctx['tool_call_id'], $outputs);
}
---------------------------------- */
