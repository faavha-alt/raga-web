<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Coach Providers
    |--------------------------------------------------------------------------
    |
    | Each user brings their own API key (Settings > AI Coach) and picks a
    | provider from this list. "default_model" is the model used in Standard
    | mode when the user leaves the model field blank; "pro_model" is used in
    | Pro mode. The user can always override with a custom model string.
    |
    */

    'providers' => [
        'anthropic' => [
            'label' => 'Claude (Anthropic)',
            'default_model' => 'claude-opus-5',
            'pro_model' => 'claude-opus-5',
        ],
        'gemini' => [
            'label' => 'Gemini (Google)',
            'default_model' => 'gemini-2.5-flash',
            'pro_model' => 'gemini-2.5-pro',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mode budgets
    |--------------------------------------------------------------------------
    |
    | Per-response token ceiling by mode. Pro allows longer, deeper answers.
    |
    */

    'modes' => [
        'standard' => ['max_tokens' => 4096, 'label' => 'Standard'],
        'pro' => ['max_tokens' => 8192, 'label' => 'Pro'],
    ],

];
