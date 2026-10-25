<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'scitrace_ai' => [
        'base_url' => env('AI_SERVICE_URL', 'http://ai:8000'),
        'token' => env('AI_INTERNAL_TOKEN'),
        'timeout' => (int) env('AI_TIMEOUT_SECONDS', 5),
        'connect_timeout' => (int) env('AI_CONNECT_TIMEOUT_SECONDS', 2),
        'extraction_timeout' => (int) env('AI_EXTRACTION_TIMEOUT_SECONDS', 120),
        'detection_timeout' => (int) env('AI_DETECTION_TIMEOUT_SECONDS', 60),
        'llm_timeout' => (int) env('AI_LLM_TIMEOUT_SECONDS', 60),
    ],

];
