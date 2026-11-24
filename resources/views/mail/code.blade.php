<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ trans('laravel-2fa::laravel-2fa.mail_subject') }}</title>
</head>
<body style="margin: 0; padding: 24px; background: #f3f4f6; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #111827;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 420px; padding: 32px; background: #ffffff; border-radius: 8px;">
                <tr>
                    <td>
                        <h1 style="margin: 0 0 16px; font-size: 20px;">{{ trans('laravel-2fa::laravel-2fa.mail_subject') }}</h1>
                        <p style="margin: 0 0 16px; color: #4b5563;">{{ trans('laravel-2fa::laravel-2fa.mail_intro') }}</p>
                        <p style="margin: 0 0 16px; font-size: 28px; font-weight: 700; letter-spacing: 6px; font-family: ui-monospace, monospace;">{{ $code }}</p>
                        <p style="margin: 0; color: #4b5563;">{{ trans('laravel-2fa::laravel-2fa.mail_expires', ['minutes' => $expires]) }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
