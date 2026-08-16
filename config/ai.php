<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Coach Providers
    |--------------------------------------------------------------------------
    |
    | Each user brings their own API key (Settings > AI Coach) and picks a
    | provider from this list. "default_model" is used whenever the user
    | leaves the model field blank.
    |
    */

    'providers' => [
        'anthropic' => [
            'label' => 'Claude (Anthropic)',
            'default_model' => 'claude-opus-5',
        ],
        'gemini' => [
            'label' => 'Gemini (Google)',
            'default_model' => 'gemini-3.7-flash',
        ],
    ],

];
