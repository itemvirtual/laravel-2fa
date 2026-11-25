<?php

return [

    // Common
    'two_factor_authentication' => 'Autenticació en dos passos',
    'code' => 'Codi',
    'recovery_code' => 'Codi de recuperació',
    'method' => 'Mètode',
    'method_email' => 'Correu electrònic',
    'method_totp' => 'Aplicació d\'autenticació',
    'send_new_code' => 'Envia un codi nou',
    'new_code_sent' => 'S\'ha enviat un codi nou a :email.',
    'store_recovery_codes' => 'Desa aquests codis de recuperació en un lloc segur. No es tornaran a mostrar i cadascun només es pot fer servir una vegada.',

    // Errors
    'invalid_code' => 'El codi d\'autenticació en dos passos no és vàlid.',
    'invalid_recovery_code' => 'El codi de recuperació no és vàlid.',
    'missing_code' => 'Introdueix un codi d\'autenticació en dos passos.',

    // Challenge
    'challenge_email_text' => 'Confirma l\'accés al teu compte introduint el codi que hem enviat a :email.',
    'challenge_totp_text' => 'Confirma l\'accés al teu compte introduint el codi de la teva aplicació d\'autenticació.',
    'challenge_recovery_text' => 'Confirma l\'accés al teu compte introduint un dels teus codis de recuperació.',
    'use_recovery_code' => 'Fes servir un codi de recuperació',
    'use_authentication_code' => 'Fes servir un codi d\'autenticació',
    'log_in' => 'Inicia la sessió',

    // Form fields
    'enable_two_factor' => 'Activa l\'autenticació en dos passos',
    'generate_new_recovery_codes' => 'Genera codis de recuperació nous',
    'download_recovery_codes' => 'Descarrega els codis de recuperació',
    'fields_totp_apps' => 'Instal·la una aplicació d\'autenticació al teu mòbil, per exemple:',
    'fields_totp_text' => 'Escaneja aquest codi QR amb la teva aplicació d\'autenticació, o introdueix la clau de configuració.',

    // Mail
    'mail_subject' => 'El teu codi de verificació',
    'mail_intro' => 'Fes servir el codi següent per completar la verificació:',
    'mail_expires' => 'El codi caduca d\'aquí a :minutes minuts. Si no has intentat iniciar la sessió, pots ignorar aquest correu.',

];
