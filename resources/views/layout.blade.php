<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name', 'Laravel') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; background: #f3f4f6; color: #111827; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-size: 15px; line-height: 1.5; }
        .l2fa-card { width: 100%; max-width: 420px; padding: 32px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, .1); }
        .l2fa-card h1 { margin: 0 0 8px; font-size: 20px; }
        .l2fa-card h2 { margin: 24px 0 8px; font-size: 16px; }
        .l2fa-text { margin: 0 0 16px; color: #4b5563; }
        .l2fa-label { display: block; margin-bottom: 4px; font-weight: 600; }
        .l2fa-input { display: block; width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 16px; }
        .l2fa-input:focus { outline: 2px solid #4f46e5; outline-offset: -1px; border-color: #4f46e5; }
        .l2fa-error { margin: 6px 0 0; color: #dc2626; font-size: 14px; }
        .l2fa-status { margin: 0 0 16px; padding: 10px 12px; background: #ecfdf5; color: #065f46; border-radius: 6px; }
        .l2fa-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 16px; }
        .l2fa-button { padding: 10px 18px; border: 0; border-radius: 6px; background: #4f46e5; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; }
        .l2fa-button:hover { background: #4338ca; }
        .l2fa-button-danger { background: #dc2626; }
        .l2fa-button-danger:hover { background: #b91c1c; }
        .l2fa-link { padding: 0; border: 0; background: none; color: #4f46e5; font-size: 14px; text-decoration: underline; cursor: pointer; }
        .l2fa-qr { display: flex; justify-content: center; margin: 16px 0; }
        .l2fa-qr svg { width: 192px; height: 192px; }
        .l2fa-secret { margin: 0 0 16px; padding: 8px 12px; background: #f9fafb; border-radius: 6px; font-family: ui-monospace, monospace; text-align: center; word-break: break-all; }
        .l2fa-codes { margin: 0 0 16px; padding: 12px 16px; background: #f9fafb; border-radius: 6px; font-family: ui-monospace, monospace; list-style: none; columns: 2; }
        .l2fa-hidden { display: none; }
        .l2fa-divider { margin: 24px 0; border: 0; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<main class="l2fa-card">
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
