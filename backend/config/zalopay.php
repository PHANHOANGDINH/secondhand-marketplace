<?php

return [
    'app_id' => env('ZALOPAY_APP_ID'),
    'key1' => env('ZALOPAY_KEY1'),
    'key2' => env('ZALOPAY_KEY2'),
    'create_url' => env('ZALOPAY_CREATE_URL', 'https://sb-openapi.zalopay.vn/v2/create'),
    'query_url' => env('ZALOPAY_QUERY_URL', 'https://sb-openapi.zalopay.vn/v2/query'),
    'return_url' => env('ZALOPAY_RETURN_URL', 'http://localhost:5173/payment/result'),
    'callback_url' => env('ZALOPAY_CALLBACK_URL'),
    'expire_seconds' => (int) env('ZALOPAY_EXPIRE_SECONDS', 900),
];
