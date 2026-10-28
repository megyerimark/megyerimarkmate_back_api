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

    // Online fizetés (karbantartási előfizetés, előleg). Kulcsok: dashboard.stripe.com → Fejlesztők → API-kulcsok.
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Üresen a Stripe-fiók alapértelmezett API-verziója érvényes.
        'api_version' => env('STRIPE_API_VERSION'),
        'currency' => 'huf',
        'api_base' => env('STRIPE_API_BASE'), // csak helyi teszteléshez
    ],

    // Bot-védelem (Cloudflare Turnstile). dash.cloudflare.com → Turnstile → Add widget.
    // Üres titkos kulccsal a védelem ki van kapcsolva (helyi fejlesztés, tesztek).
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        // Vesszővel elválasztott domainek, ahonnan a widget kulcsa elfogadható (üres = bármelyik).
        'hostnames' => env('TURNSTILE_HOSTNAMES'),
    ],

];
