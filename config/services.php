<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],
    'auth_whitelist' => array_filter(explode(',', env('AUTH_WHITELIST', ''))),

    'helloasso' => [
        'client_id'     => env('HELLOASSO_CLIENT_ID'),
        'client_secret' => env('HELLOASSO_CLIENT_SECRET'),
        'org_slug'      => env('HELLOASSO_ORG_SLUG', 'comite-regional-des-etudiants-en-medecine-de-poitiers'),
        'form_slug'     => env('HELLOASSO_FORM_SLUG', 'examen-blanc-s2'),
        'form_type'     => env('HELLOASSO_FORM_TYPE', 'Event'),
        'webhook_secret' => env('HELLOASSO_WEBHOOK_SECRET'),
        'inscription_forms' => [
            'las1_adherent'           => env('HA_FORM_LAS1_ADHERENT'),
            'las1_adherent_sans_tuto' => env('HA_FORM_LAS1_SANS_TUTO'),
            'las1_non_adherent'       => env('HA_FORM_LAS1_NON_ADHERENT'),
            'las2_adherent'           => env('HA_FORM_LAS2_ADHERENT'),
            'las2_adherent_sans_tuto' => env('HA_FORM_LAS2_SANS_TUTO'),
            'las2_non_adherent'       => env('HA_FORM_LAS2_NON_ADHERENT'),
        ],
    ],

];
