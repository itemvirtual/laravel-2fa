<?php

return [

    // Common
    'two_factor_authentication' => 'Two factor authentication',
    'code' => 'Code',
    'recovery_code' => 'Recovery code',
    'method' => 'Method',
    'method_email' => 'Email',
    'method_totp' => 'Authenticator application',
    'send_new_code' => 'Send a new code',
    'new_code_sent' => 'A new code has been sent to :email.',
    'store_recovery_codes' => 'Store these recovery codes in a safe place. They will not be shown again and each one can be used only once.',

    // Errors
    'invalid_code' => 'The provided two factor authentication code was invalid.',
    'invalid_recovery_code' => 'The provided two factor recovery code was invalid.',
    'missing_code' => 'Please provide a two factor authentication code.',

    // Challenge
    'challenge_email_text' => 'Please confirm access to your account by entering the code we sent to :email.',
    'challenge_totp_text' => 'Please confirm access to your account by entering the authentication code provided by your authenticator application.',
    'challenge_recovery_text' => 'Please confirm access to your account by entering one of your emergency recovery codes.',
    'use_recovery_code' => 'Use a recovery code',
    'use_authentication_code' => 'Use an authentication code',
    'log_in' => 'Log in',

    // Form fields
    'enable_two_factor' => 'Enable two factor authentication',
    'generate_new_recovery_codes' => 'Generate new recovery codes',
    'download_recovery_codes' => 'Download recovery codes',
    'fields_totp_apps' => 'Install an authenticator application on your phone, for example:',
    'fields_totp_text' => 'Scan this QR code with your authenticator application, or enter the setup key.',

    // Mail
    'mail_subject' => 'Your verification code',
    'mail_intro' => 'Use the following code to complete the verification:',
    'mail_expires' => 'The code expires in :minutes minutes. If you did not try to sign in, you can ignore this email.',

];
