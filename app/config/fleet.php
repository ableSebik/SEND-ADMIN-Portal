<?php

return [
    'email_enabled' => env('FLEET_EMAIL_ENABLED', false),
    // Endpoint contract: authenticated JSON POST {to, message, idempotency_key}.
    'sms_url' => env('FLEET_SMS_URL'),
    'sms_token' => env('FLEET_SMS_TOKEN'),
];
