<?php

return [
    'account_sid' => env('TWILIO_ACCOUNT_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),
    'from' => env('TWILIO_FROM'),
    'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
    'reminder_minutes' => (int) env('TWILIO_REMINDER_MINUTES', 60),
];
