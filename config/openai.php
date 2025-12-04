<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | Your OpenAI API key. You can find this in your OpenAI dashboard.
    |
    */

    'api_key' => env('OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    |
    | The default OpenAI model to use for generating news articles.
    | This can be overridden in the OpenAI settings component.
    |
    */

    'default_model' => env('OPENAI_DEFAULT_MODEL', 'o1-preview'),

    /*
    |--------------------------------------------------------------------------
    | Available Models
    |--------------------------------------------------------------------------
    |
    | List of available OpenAI models that can be selected.
    |
    */

    'available_models' => [
        'gpt-5' => 'GPT-5',
        'gpt5-mini' => 'GPT-5 Mini',
        'gpt5-nano' => 'GPT-5 Nano',
        'gpt-4' => 'GPT-4',
        'gpt-4-turbo' => 'GPT-4 Turbo',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        'o1-preview' => 'O1 Preview',
        'o1-mini' => 'O1 Mini',
        'o1-nano' => 'O1 Nano',
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout in seconds for OpenAI API requests.
    |
    */

    'timeout' => env('OPENAI_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Max Tokens
    |--------------------------------------------------------------------------
    |
    | Maximum number of tokens to generate in the response.
    | For GPT-5 and O1 models, this should be higher as they use reasoning tokens.
    |
    */

    'max_tokens' => env('OPENAI_MAX_TOKENS', 2000),
    
    /*
    |--------------------------------------------------------------------------
    | Max Completion Tokens
    |--------------------------------------------------------------------------
    |
    | Maximum number of completion tokens for GPT-5 and O1 models.
    | These models use reasoning tokens first, so this should be higher.
    |
    */

    'max_completion_tokens' => env('OPENAI_MAX_COMPLETION_TOKENS', 4000),
];

