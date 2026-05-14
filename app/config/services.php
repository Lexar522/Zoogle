<?php

$googleOAuthRedirect = env('GOOGLE_REDIRECT_URI');
if (! filled($googleOAuthRedirect)) {
    $appUrlBase = (string) env('APP_URL', 'http://127.0.0.1:8000');
    if ($appUrlBase === '') {
        $appUrlBase = 'http://127.0.0.1:8000';
    }
    $googleOAuthRedirect = rtrim($appUrlBase, '/').'/auth/google/callback';
}

$googleOAuthGuzzle = [];
$googleHttpCainfo = env('GOOGLE_HTTP_CAINFO');
if (is_string($googleHttpCainfo) && ($googleHttpCainfo = trim($googleHttpCainfo)) !== '') {
    // Guzzle: шлях до cacert.pem (часто потрібно на Windows, інакше cURL error 60).
    // Можна абсолютний шлях або відносний від кореня проєкту, напр. storage/certs/cacert.pem
    $isAbsolute = str_starts_with($googleHttpCainfo, '/')
        || str_starts_with($googleHttpCainfo, '\\')
        || (strlen($googleHttpCainfo) > 2
            && ctype_alpha($googleHttpCainfo[0])
            && $googleHttpCainfo[1] === ':'
            && ($googleHttpCainfo[2] === '\\' || $googleHttpCainfo[2] === '/'));
    $googleOAuthGuzzle['verify'] = $isAbsolute ? $googleHttpCainfo : base_path($googleHttpCainfo);
}

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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'wayforpay' => [
        'merchant_account' => env('WAYFORPAY_MERCHANT_ACCOUNT'),
        // У документації WayForPay поле називається merchantSecretKey; підтримуємо обидві назви .env.
        'secret_key' => env('WAYFORPAY_SECRET_KEY', env('WAYFORPAY_MERCHANT_SECRET_KEY')),
        'merchant_domain' => env('WAYFORPAY_MERCHANT_DOMAIN'),
    ],

    'liqpay' => [
        'public_key' => env('LIQPAY_PUBLIC_KEY'),
        'private_key' => env('LIQPAY_PRIVATE_KEY'),
        'sandbox' => filter_var(env('LIQPAY_SANDBOX', true), FILTER_VALIDATE_BOOLEAN),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => $googleOAuthRedirect,
        'guzzle' => $googleOAuthGuzzle,
        /** Ключ Maps JavaScript API для карти на чекауті (окремо від OAuth). Обмежте в Cloud Console по HTTP referrers. */
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'nova_poshta' => [
        'api_key' => env('NOVA_POSHTA_API_KEY'),
    ],

];
