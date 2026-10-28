<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    /*
     * OPAY (https://opay.dev/docs/v1). The sandbox is the default, so nothing
     * reaches the real service until OPAY_BASE_URL is set on purpose. Leave
     * OPAY_TEST_USER_ID empty in production: it turns every payment into a
     * test payment.
     */
    'opay' => [
        'website_id' => env('OPAY_WEBSITE_ID'),
        'signature_password' => env('OPAY_SIGNATURE_PASSWORD'),
        'base_url' => env('OPAY_BASE_URL', 'https://sandbox-api.opay.eu'),
        'test_user_id' => env('OPAY_TEST_USER_ID'),
        // The groups and banks offered. banklink_citadele and the offline
        // methods (cash, banktransfer) are left out on purpose: they need a
        // longer payment window than the 15 minutes an order's stock is held.
        'show_channels' => env('OPAY_SHOW_CHANNELS', 'pis,card,banklink_swedbank,banklink_seb,banklink_dnb,banklink_sb,banklink_medbank'),
        'timeout' => (int) env('OPAY_TIMEOUT', 8),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
