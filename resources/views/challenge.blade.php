@extends('laravel-2fa::layout')

@section('title', trans('laravel-2fa::laravel-2fa.two_factor_authentication'))

@section('content')
    <h1>{{ trans('laravel-2fa::laravel-2fa.two_factor_authentication') }}</h1>
    
    @if (session('laravel-2fa.status') === 'code-sent')
        <p class="l2fa-status">{{ trans('laravel-2fa::laravel-2fa.new_code_sent', ['email' => $email]) }}</p>
    @endif
    
    <form method="POST" action="{{ $action }}" id="l2fa-challenge" data-mode="{{ $errors->has('recovery_code') ? 'recovery' : 'code' }}">
        @csrf
        
        <div id="l2fa-mode-code">
            @if ($method === 'email')
                <p class="l2fa-text">{{ trans('laravel-2fa::laravel-2fa.challenge_email_text', ['email' => $email]) }}</p>
            @else
                <p class="l2fa-text">{{ trans('laravel-2fa::laravel-2fa.challenge_totp_text') }}</p>
            @endif
            <label class="l2fa-label" for="l2fa-code">{{ trans('laravel-2fa::laravel-2fa.code') }}</label>
            <input class="l2fa-input" id="l2fa-code" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="12" autocomplete="one-time-code" autofocus>
            @error('code')
            <p class="l2fa-error">{{ $message }}</p>
            @enderror
        </div>
        
        <div id="l2fa-mode-recovery" class="l2fa-hidden">
            <p class="l2fa-text">{{ trans('laravel-2fa::laravel-2fa.challenge_recovery_text') }}</p>
            <label class="l2fa-label" for="l2fa-recovery-code">{{ trans('laravel-2fa::laravel-2fa.recovery_code') }}</label>
            <input class="l2fa-input" id="l2fa-recovery-code" type="text" name="recovery_code" autocomplete="off" disabled>
            @error('recovery_code')
            <p class="l2fa-error">{{ $message }}</p>
            @enderror
        </div>
        
        <div class="l2fa-actions">
            <button type="button" class="l2fa-link" id="l2fa-toggle-recovery" onclick="l2faSetMode('recovery')">{{ trans('laravel-2fa::laravel-2fa.use_recovery_code') }}</button>
            <button type="button" class="l2fa-link l2fa-hidden" id="l2fa-toggle-code" onclick="l2faSetMode('code')">{{ trans('laravel-2fa::laravel-2fa.use_authentication_code') }}</button>
            <button type="submit" class="l2fa-button">{{ trans('laravel-2fa::laravel-2fa.log_in') }}</button>
        </div>
    </form>
    
    @if ($method === 'email')
        <form method="POST" action="{{ $resendAction }}">
            @csrf
            <div class="l2fa-actions">
                <button type="submit" class="l2fa-link">{{ trans('laravel-2fa::laravel-2fa.send_new_code') }}</button>
            </div>
        </form>
    @endif
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
