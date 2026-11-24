<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Global options
    |--------------------------------------------------------------------------
    |
    | issuer: name shown in the authenticator app
    | window: accepted drift, in 30 second periods, before and after the current one
    | recovery_codes: number of recovery codes generated for each user
    |
    */
    'issuer' => env('LARAVEL_2FA_ISSUER', env('APP_NAME', 'Laravel')),
    'window' => (int)env('LARAVEL_2FA_WINDOW', 1),
    'recovery_codes' => (int)env('LARAVEL_2FA_RECOVERY_CODES', 8),

    /*
    |--------------------------------------------------------------------------
    | Email method
    |--------------------------------------------------------------------------
    |
    | expires: minutes before an email code expires
    | length: number of digits of the email code
    | max_attempts: wrong attempts before the email code is discarded
    |
    */
    'email' => [
        'expires' => (int)env('LARAVEL_2FA_EMAIL_EXPIRES', 10),
        'length' => (int)env('LARAVEL_2FA_EMAIL_LENGTH', 6),
        'max_attempts' => (int)env('LARAVEL_2FA_EMAIL_MAX_ATTEMPTS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    |
    | One entry per auth guard (config/auth.php). Each guard has its own routes,
    | session keys and views. The table is resolved through the guard provider.
    | Guards not listed here have no 2FA.
    |
    | Only users with two_factor_enabled = true use 2FA
    |
    | methods: methods the user can choose in the profile form (totp, email)
    |
    */
    'guards' => [

        'web' => [
            'methods' => ['totp', 'email'],
            'prefix' => 'two-factor',
            'middleware' => ['web'],
            'login_route' => 'login',
            'redirect' => '/dashboard',
            'views' => [
                'challenge' => 'laravel-2fa::challenge',
                'fields' => 'laravel-2fa::fields',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Views path
    |--------------------------------------------------------------------------
    |
    | Directory inside resources/views where the views are published.
    | Views found there override the package ones (laravel-2fa::)
    |
    */
    'views_path' => 'laravel-2fa',

    /*
    |--------------------------------------------------------------------------
    | Doctrine xml-mappings path
    |--------------------------------------------------------------------------
    |
    | Used by the laravel-2fa:doctrine-mappings command
    |
    */
    'xml_mappings_path' => base_path('database/doctrine/xml-mappings'),

    /*
    |--------------------------------------------------------------------------
    | Logs
    |--------------------------------------------------------------------------
    |
    | Save logs when the laravel-2fa:doctrine-mappings command modifies
    | the xml-mappings. The 'logging' array is the log channel configuration
    |
    */
    'save_logs' => true,
    'logging' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel-2fa.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
        'replace_placeholders' => true,
    ],

];
