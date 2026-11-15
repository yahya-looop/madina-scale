<?php

return [
    'otp_ttl_minutes' => (int) env('CUSTOMER_OTP_TTL_MINUTES', 5),
    'otp_resend_seconds' => (int) env('CUSTOMER_OTP_RESEND_SECONDS', 60),
    'otp_max_attempts' => (int) env('CUSTOMER_OTP_MAX_ATTEMPTS', 5),
    'log_otp_when_sms_unconfigured' => (bool) env('CUSTOMER_OTP_LOG_UNCONFIGURED', true),
];
