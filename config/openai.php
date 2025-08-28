<?php

return [
    'api_key'     => env('OPENAI_API_KEY',''),
    'audio_model' => env('OPENAI_AUDIO_MODEL', 'gpt-4o-transcribe'),
];