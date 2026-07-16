<?php

return [
    'driver' => env('PHONE_VERIFICATION_DRIVER', 'log'),
    'code_ttl_seconds' => (int) env('PHONE_VERIFICATION_CODE_TTL_SECONDS', 600),
    'resend_cooldown_seconds' => (int) env('PHONE_VERIFICATION_RESEND_COOLDOWN_SECONDS', 60),
    'max_attempts' => (int) env('PHONE_VERIFICATION_MAX_ATTEMPTS', 5),
];
