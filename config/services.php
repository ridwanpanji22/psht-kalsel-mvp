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

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),

        /*
        |--------------------------------------------------------------------------
        | Firebase Web (client) configuration
        |--------------------------------------------------------------------------
        |
        | These values are public and embedded into the authentication pages so
        | the Firebase Web JS SDK can sign operators in. They may be supplied as
        | either `FIREBASE_*` or `VITE_FIREBASE_*` variables.
        |
        */
        'client' => [
            'api_key' => env('VITE_FIREBASE_API_KEY', env('FIREBASE_API_KEY')),
            'auth_domain' => env('VITE_FIREBASE_AUTH_DOMAIN', env('FIREBASE_AUTH_DOMAIN')),
            'project_id' => env('VITE_FIREBASE_PROJECT_ID', env('FIREBASE_PROJECT_ID')),
            'storage_bucket' => env('VITE_FIREBASE_STORAGE_BUCKET', env('FIREBASE_STORAGE_BUCKET')),
            'messaging_sender_id' => env('VITE_FIREBASE_MESSAGING_SENDER_ID', env('FIREBASE_MESSAGING_SENDER_ID')),
            'app_id' => env('VITE_FIREBASE_APP_ID', env('FIREBASE_APP_ID')),
        ],
    ],

];
