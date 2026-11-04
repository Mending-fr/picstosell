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

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    ],

    'fal' => [
        'key' => env('FAL_KEY'),
        'cutout_model' => env('FAL_CUTOUT_MODEL', 'fal-ai/birefnet/v2'),
        'cutout_cost_usd' => env('FAL_CUTOUT_COST_USD', '0.0005'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        // Rangement des photos : un modèle rapide suffit.
        'vision_model' => env('ANTHROPIC_VISION_MODEL', 'claude-haiku-5-5'),
    ],

    'autoways' => [
        'token' => env('AUTOWAYS_TOKEN'),
        'url' => env('AUTOWAYS_URL', 'https://app.auto-ways.net/api/v1/fr'),
    ],

    'vigie' => [
        'script' => env('VIGIE_SCRIPT'),
    ],

    'plate' => [
        'provider' => env('PLATE_PROVIDER', 'fake'),
        'api_key' => env('PLATE_API_KEY'),
        'url' => env('PLATE_API_URL', 'https://api.apiplaqueimmatriculation.com/carte-grise'),
    ],

];
