<?php

return [
    'default_expiration_days' => env('INVITATION_DEFAULT_EXPIRATION_DAYS', 7),
    'max_expiration_days' => env('INVITATION_MAX_EXPIRATION_DAYS', 90),
    'token_length' => env('INVITATION_TOKEN_LENGTH', 64),
    'max_pending_per_server' => env('INVITATION_MAX_PENDING_PER_SERVER', 50),
    'rate_limit' => [
        'create_per_hour' => env('INVITATION_RATE_LIMIT_PER_HOUR', 10),
        'validate_per_minute' => 20,
        'accept_per_hour' => 5,
    ],
];
