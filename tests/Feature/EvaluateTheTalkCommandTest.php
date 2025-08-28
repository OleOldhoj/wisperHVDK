<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EvaluateTheTalkCommandTest extends TestCase
{
    public function testOutputsAssistantReply(): void
    {
        if (!defined('OA_LIB_PATH')) {
            define('OA_LIB_PATH', base_path('script/tests/stub_openai_assistant.php'));
        }
        putenv('OPENAI_API_KEY=dummy');

        $exitCode = Artisan::call('EvaluateTheTalk:talk');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('greeting_quality', Artisan::output());
    }
}
