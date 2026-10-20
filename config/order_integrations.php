<?php

return [
    'sms' => [
        'enabled' => (bool) env('ORDER_SMS_ENABLED', false),
        'driver' => env('ORDER_SMS_DRIVER', 'generic'),
        'endpoint' => env('ORDER_SMS_ENDPOINT'),
        'api_key' => env('ORDER_SMS_API_KEY'),
        'api_key_header' => env('ORDER_SMS_API_KEY_HEADER', 'Authorization'),
        'api_key_prefix' => env('ORDER_SMS_API_KEY_PREFIX', 'Bearer '),
        'auth_mode' => env('ORDER_SMS_AUTH_MODE', 'header'),
        'api_key_field' => env('ORDER_SMS_API_KEY_FIELD', 'api_key'),
        'sender_field' => env('ORDER_SMS_SENDER_FIELD', 'sender_id'),
        'to_field' => env('ORDER_SMS_TO_FIELD', 'to'),
        'message_field' => env('ORDER_SMS_MESSAGE_FIELD', 'message'),
        'timeout' => (int) env('ORDER_SMS_TIMEOUT', 10),
        'sender_id' => env('ORDER_SMS_SENDER_ID'),
    ],
    'couriers' => [
        'steadfast' => [
            'enabled' => (bool) env('STEADFAST_ENABLED', false),
            'base_url' => env('STEADFAST_BASE_URL', 'https://portal.packzy.com/api/v1'),
            'api_key' => env('STEADFAST_API_KEY'), 'secret_key' => env('STEADFAST_SECRET_KEY'),
            'timeout' => (int) env('COURIER_API_TIMEOUT', 15),
        ],
        'redx' => [
            'enabled' => (bool) env('REDX_ENABLED', false),
            'base_url' => env('REDX_BASE_URL', 'https://openapi.redx.com.bd/v1.0.0-beta'),
            'access_token' => env('REDX_ACCESS_TOKEN'), 'pickup_store_id' => env('REDX_PICKUP_STORE_ID'),
            'timeout' => (int) env('COURIER_API_TIMEOUT', 15),
        ],
        'pathao' => [
            'enabled' => (bool) env('PATHAO_ENABLED', false),
            'base_url' => env('PATHAO_BASE_URL'), 'access_token' => env('PATHAO_ACCESS_TOKEN'),
            'store_id' => env('PATHAO_STORE_ID'), 'timeout' => (int) env('COURIER_API_TIMEOUT', 15),
        ],
        'carrybee' => [
            'enabled' => (bool) env('CARRYBEE_ENABLED', false),
            'base_url' => env('CARRYBEE_BASE_URL'), 'api_token' => env('CARRYBEE_API_TOKEN'),
            'create_path' => env('CARRYBEE_CREATE_PATH', '/shipments'),
            'timeout' => (int) env('COURIER_API_TIMEOUT', 15),
        ],
        'custom' => [
            'enabled' => (bool) env('CUSTOM_COURIER_ENABLED', false),
            'base_url' => env('CUSTOM_COURIER_BASE_URL'), 'api_token' => env('CUSTOM_COURIER_API_TOKEN'),
            'create_path' => env('CUSTOM_COURIER_CREATE_PATH', '/shipments'),
            'timeout' => (int) env('COURIER_API_TIMEOUT', 15),
        ],
    ],
    'risk' => [
        'review_score' => (int) env('ORDER_RISK_REVIEW_SCORE', 35),
        'high_score' => (int) env('ORDER_RISK_HIGH_SCORE', 65),
        'phone_velocity_count' => (int) env('ORDER_RISK_PHONE_VELOCITY_COUNT', 3),
    ],
    'verification' => [
        'enabled' => (bool) env('ORDER_PHONE_VERIFICATION_ENABLED', true),
        'ttl_minutes' => (int) env('ORDER_PHONE_OTP_TTL_MINUTES', 5),
        'max_attempts' => (int) env('ORDER_PHONE_OTP_MAX_ATTEMPTS', 5),
    ],
    'webhook_secret' => env('ORDER_COURIER_WEBHOOK_SECRET'),
];
