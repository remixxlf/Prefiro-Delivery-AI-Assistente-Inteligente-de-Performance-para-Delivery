<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Provedor de IA padrão
    |--------------------------------------------------------------------------
    | Suporte: "openai" | "gemini" | "anthropic"
    */
    'provider' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | OpenAI
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model'   => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'max_tokens'  => (int) env('OPENAI_MAX_TOKENS', 2000),
        'temperature' => (float) env('OPENAI_TEMPERATURE', 0.3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gemini (Google)
    |--------------------------------------------------------------------------
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model'   => env('GEMINI_MODEL', 'gemini-1.5-flash'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic (Claude)
    |--------------------------------------------------------------------------
    */
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model'   => env('ANTHROPIC_MODEL', 'claude-3-haiku-20240307'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limites e Controle de Custo
    |--------------------------------------------------------------------------
    */
    'token_limit_per_request' => (int) env('AI_TOKEN_LIMIT_PER_REQUEST', 4000),
    'cost_log_enabled'        => env('AI_COST_LOG_ENABLED', true),
    'cache_ttl_seconds'       => (int) env('AI_CACHE_TTL_SECONDS', 3600),
    'rate_limit_per_minute'   => (int) env('AI_RATE_LIMIT_PER_MINUTE', 20),

    /*
    |--------------------------------------------------------------------------
    | Preço estimado por 1K tokens (USD) — para log de custo
    |--------------------------------------------------------------------------
    */
    'pricing' => [
        'openai' => [
            'gpt-4o-mini'    => ['input' => 0.00015, 'output' => 0.00060],
            'gpt-4o'         => ['input' => 0.00500, 'output' => 0.01500],
        ],
        'gemini' => [
            'gemini-1.5-flash' => ['input' => 0.00000, 'output' => 0.00000],
        ],
        'anthropic' => [
            'claude-3-haiku-20240307' => ['input' => 0.00025, 'output' => 0.00125],
        ],
    ],
];