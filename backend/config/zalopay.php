<?php

return [
    'app_id' => env('ZALOPAY_APP_ID'),
    'key1' => env('ZALOPAY_KEY1'),
    'key2' => env('ZALOPAY_KEY2'),
    'create_url' => env('ZALOPAY_CREATE_URL', 'https://sb-openapi.zalopay.vn/v2/create'),
    'query_url' => env('ZALOPAY_QUERY_URL', 'https://sb-openapi.zalopay.vn/v2/query'),
    // Where ZaloPay sends the buyer back: the frontend result page (it re-checks the status with the server).
    // ZALOPAY_RETURN_URL is the older name and still read.
    'redirect_url' => env('ZALOPAY_REDIRECT_URL') ?: (env('ZALOPAY_RETURN_URL') ?: rtrim(explode(',', (string) env('FRONTEND_URLS', 'http://localhost:5173'))[0], '/').'/payment/result'),
    // Server-to-server notification. Must be reachable from the internet (see scripts/zalopay-tunnel.ps1).
    'callback_url' => env('ZALOPAY_CALLBACK_URL') ?: rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/v1/payments/zalopay/callback',
    'expire_seconds' => (int) env('ZALOPAY_EXPIRE_SECONDS', 900),
];
