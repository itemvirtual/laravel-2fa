@extends('laravel-2fa::layout')

@section('title', __('Two factor authentication'))

@section('content')
    <h1>{{ __('Two factor authentication') }}</h1>

    @if (session('laravel-2fa.status') === 'two-factor-confirmed')
        <p class="l2fa-status">{{ __('Two factor authentication has been enabled.') }}</p>
    @elseif (session('laravel-2fa.status') === 'recovery-codes-generated')
        <p class="l2fa-status">{{ __('New recovery codes have been generated.') }}</p>
    @elseif (session('laravel-2fa.status') === 'two-factor-reset')
        <p class="l2fa-status">{{ __('Two factor authentication has been reset. Please set it up again.') }}</p>
    @endif

    @if (!$confirmed)
        <p class="l2fa-text">{{ __('Scan the following QR code using your authenticator application, or enter the setup key, then confirm with the generated code.') }}</p>

        <div class="l2fa-qr">{!! $qrCode !!}</div>
        <p class="l2fa-secret">{{ $secret }}</p>

        <form method="POST" action="{{ $routes['confirm'] }}">
            @csrf
            <label class="l2fa-label" for="l2fa-code">{{ __('Code') }}</label>
            <input class="l2fa-input" id="l2fa-code" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus>
            @error('code')
                <p class="l2fa-error">{{ $message }}</p>
            @enderror
            <div class="l2fa-actions">
                <span></span>
                <button type="submit" class="l2fa-button">{{ __('Confirm') }}</button>
            </div>
        </form>
    @else
        <p class="l2fa-text">{{ __('Two factor authentication is enabled for your account.') }}</p>

        @if (!empty($recoveryCodes))
            <h2>{{ __('Recovery codes') }}</h2>
            <p class="l2fa-text">{{ __('Store these recovery codes in a safe place. They will not be shown again and each one can be used only once.') }}</p>
            <ul class="l2fa-codes">
                @foreach ($recoveryCodes as $recoveryCode)
                    <li>{{ $recoveryCode }}</li>
                @endforeach
            </ul>
        @endif

        @error('code')
            <p class="l2fa-error">{{ $message }}</p>
        @enderror

        <h2>{{ __('Regenerate recovery codes') }}</h2>
        <form method="POST" action="{{ $routes['recoveryCodes'] }}">
            @csrf
            <label class="l2fa-label" for="l2fa-regenerate-code">{{ __('Code') }}</label>
            <input class="l2fa-input" id="l2fa-regenerate-code" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required>
            <div class="l2fa-actions">
                <span></span>
                <button type="submit" class="l2fa-button">{{ __('Regenerate') }}</button>
            </div>
        </form>

        <hr class="l2fa-divider">

        <h2>{{ __('Reset two factor authentication') }}</h2>
        <p class="l2fa-text">{{ __('Use this if you changed your device. You will need to set it up again.') }}</p>
        <form method="POST" action="{{ $routes['reset'] }}" onsubmit="return l2faConfirmReset();">
            @csrf
            @method('DELETE')
            <label class="l2fa-label" for="l2fa-reset-code">{{ __('Code') }}</label>
            <input class="l2fa-input" id="l2fa-reset-code" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required>
            <div class="l2fa-actions">
                <span></span>
                <button type="submit" class="l2fa-button l2fa-button-danger">{{ __('Reset') }}</button>
            </div>
        </form>

        <hr class="l2fa-divider">

        <a class="l2fa-link" href="{{ url($redirect) }}">{{ __('Continue') }}</a>
    @endif
@endsection

@push('scripts')
    <script>
        function l2faConfirmReset() {
            return window.confirm({{ \Illuminate\Support\Js::from(__('Are you sure you want to reset two factor authentication?')) }});
        }
    </script>
@endpush
