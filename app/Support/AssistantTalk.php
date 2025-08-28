<?php
// app/Support/AssistantTalk.php
// Send arbitrary text to the OpenAI assistant and return its reply.

if (!defined('OA_LIB_PATH')) {
    require_once base_path('public_html/openai_assistant.php');
} else {
    require_once OA_LIB_PATH;
}

/**
 * Send text to the default assistant and capture the first reply.
 *
 * @param string      $text        Message content for the assistant
 * @param string|null $assistantId Optional assistant identifier
 * @return array{reply?:string,error?:string} Reply text or error
 */
function assistant_talk(string $text, ?string $assistantId = null): array
{
    try {
        $apiKey = getenv('OPENAI_API_KEY');
        if ($apiKey === false || $apiKey === '') {
            return ['error' => 'missing API key'];
        }

        $model = getenv('OPENAI_MODEL') ?: 'gpt-4o';
        $ctx   = oa_init_ctx($apiKey, $assistantId);

        if (empty($assistantId)) {
            $assistantId = oa_create_assistant($ctx, 'Generic Assistant', 'You are a helpful assistant.', [], $model);
        } else {
            $ctx['assistant_id'] = $assistantId;
        }

        $threadId = oa_create_thread($ctx, $text, 'user');
        $runId    = oa_run_thread($ctx, $threadId);
        if (!$runId) {
            return ['error' => 'run failed'];
        }

        $messages = oa_list_thread_messages($ctx, $threadId);
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'assistant') {
                foreach ($msg['content'] ?? [] as $content) {
                    $candidate = $content['text']['value'] ?? ($content['text'] ?? null);
                    if (is_string($candidate) && $candidate !== '') {
                        return ['reply' => $candidate];
                    }
                }
            }
        }

        return ['error' => 'no assistant response'];
    } catch (Throwable $e) {
        fwrite(STDERR, 'Assistant call failed: ' . $e->getMessage() . "\n");
        return ['error' => $e->getMessage()];
    }
}
