setup:{{ $guard }}:{{ $confirmed ? 'confirmed' : 'pending' }}
secret:{{ $secret }}
qr:{{ $qrCode ? 'yes' : 'no' }}
codes:{{ implode(',', $recoveryCodes) }}
