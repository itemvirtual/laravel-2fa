<?php

return [

    // Common
    'two_factor_authentication' => 'Autenticación en dos pasos',
    'code' => 'Código',
    'recovery_code' => 'Código de recuperación',
    'method' => 'Método',
    'method_email' => 'Email',
    'method_totp' => 'Aplicación de autenticación',
    'send_new_code' => 'Enviar un código nuevo',
    'new_code_sent' => 'Se ha enviado un código nuevo a :email.',
    'store_recovery_codes' => 'Guarda estos códigos de recuperación en un lugar seguro. No se volverán a mostrar y cada uno solo se puede usar una vez.',

    // Errors
    'invalid_code' => 'El código de autenticación en dos pasos no es válido.',
    'invalid_recovery_code' => 'El código de recuperación no es válido.',
    'missing_code' => 'Introduce un código de autenticación en dos pasos.',

    // Challenge
    'challenge_email_text' => 'Confirma el acceso a tu cuenta introduciendo el código que hemos enviado a :email.',
    'challenge_totp_text' => 'Confirma el acceso a tu cuenta introduciendo el código de tu aplicación de autenticación.',
    'challenge_recovery_text' => 'Confirma el acceso a tu cuenta introduciendo uno de tus códigos de recuperación.',
    'use_recovery_code' => 'Usar un código de recuperación',
    'use_authentication_code' => 'Usar un código de autenticación',
    'log_in' => 'Iniciar sesión',

    // Form fields
    'enable_two_factor' => 'Activar la autenticación en dos pasos',
    'generate_new_recovery_codes' => 'Generar nuevos códigos de recuperación',
    'download_recovery_codes' => 'Descargar códigos de recuperación',
    'fields_totp_apps' => 'Instala una aplicación de autenticación en tu móvil, por ejemplo:',
    'fields_totp_text' => 'Escanea este código QR con tu aplicación de autenticación, o introduce la clave de configuración.',

    // Mail
    'mail_subject' => 'Tu código de verificación',
    'mail_intro' => 'Usa el siguiente código para completar la verificación:',
    'mail_expires' => 'El código caduca en :minutes minutos. Si no has intentado iniciar sesión, puedes ignorar este email.',

];
