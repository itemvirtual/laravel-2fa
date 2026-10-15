@extends('laravel-2fa::layout')

@section('title', __('Two factor authentication'))

@section('content')
    <h1>{{ __('Two factor authentication') }}</h1>

    <form method="POST" action="{{ $action }}" id="l2fa-challenge" data-mode="{{ $errors->has('recovery_code') ? 'recovery' : 'code' }}">
        @csrf

        <div id="l2fa-mode-code">
            <p class="l2fa-text">{{ __('Please confirm access to your account by entering the authentication code provided by your authenticator application.') }}</p>
            <label class="l2fa-label" for="l2fa-code">{{ __('Code') }}</label>
            <input class="l2fa-input" id="l2fa-code" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus>
            @error('code')
                <p class="l2fa-error">{{ $message }}</p>
            @enderror
        </div>

        <div id="l2fa-mode-recovery" class="l2fa-hidden">
            <p class="l2fa-text">{{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}</p>
            <label class="l2fa-label" for="l2fa-recovery-code">{{ __('Recovery code') }}</label>
            <input class="l2fa-input" id="l2fa-recovery-code" type="text" name="recovery_code" autocomplete="off" disabled>
            @error('recovery_code')
                <p class="l2fa-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="l2fa-actions">
            <button type="button" class="l2fa-link" id="l2fa-toggle-recovery" onclick="l2faSetMode('recovery')">{{ __('Use a recovery code') }}</button>
            <button type="button" class="l2fa-link l2fa-hidden" id="l2fa-toggle-code" onclick="l2faSetMode('code')">{{ __('Use an authentication code') }}</button>
            <button type="submit" class="l2fa-button">{{ __('Log in') }}</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        function l2faSetMode(mode) {
            var recovery = mode === 'recovery';
            var code = document.getElementById('l2fa-code');
            var recoveryCode = document.getElementById('l2fa-recovery-code');

            document.getElementById('l2fa-mode-code').classList.toggle('l2fa-hidden', recovery);
            document.getElementById('l2fa-mode-recovery').classList.toggle('l2fa-hidden', !recovery);
            document.getElementById('l2fa-toggle-recovery').classList.toggle('l2fa-hidden', recovery);
            document.getElementById('l2fa-toggle-code').classList.toggle('l2fa-hidden', !recovery);

            code.disabled = recovery;
            recoveryCode.disabled = !recovery;
            (recovery ? recoveryCode : code).focus();
        }

        l2faSetMode(document.getElementById('l2fa-challenge').getAttribute('data-mode'));
    </script>
@endpush
