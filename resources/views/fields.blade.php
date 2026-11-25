<div class="l2fa-fields">
    <div class="l2fa-field">
        <input type="checkbox" id="l2fa-enabled-{{ $guard }}" name="two_factor_enabled" value="1" @checked(old('two_factor_enabled', session()->hasOldInput() ? false : $enabled))>
        <label for="l2fa-enabled-{{ $guard }}">{{ trans('laravel-2fa::laravel-2fa.enable_two_factor') }}</label>
    </div>

    <div class="l2fa-field">
        <label for="l2fa-method-{{ $guard }}">{{ trans('laravel-2fa::laravel-2fa.method') }}</label>
        <select id="l2fa-method-{{ $guard }}" name="two_factor_method">
            @foreach ($methods as $option)
                <option value="{{ $option }}" @selected(old('two_factor_method', $method) === $option)>{{ $option === 'email' ? trans('laravel-2fa::laravel-2fa.method_email') : trans('laravel-2fa::laravel-2fa.method_totp') }}</option>
            @endforeach
        </select>
    </div>

    @if ($qrCode)
        @if (config('laravel-2fa.authenticator_apps'))
            <p class="l2fa-text">{{ trans('laravel-2fa::laravel-2fa.fields_totp_apps') }}</p>
            <ul class="l2fa-apps">
                @foreach (config('laravel-2fa.authenticator_apps') as $appName => $appUrl)
                    <li><a class="l2fa-link" href="{{ $appUrl }}" target="_blank" rel="noopener">{{ $appName }}</a></li>
                @endforeach
            </ul>
        @endif
        <p class="l2fa-text">{{ trans('laravel-2fa::laravel-2fa.fields_totp_text') }}</p>
        <div class="l2fa-qr">{!! $qrCode !!}</div>
        <p class="l2fa-secret">{{ $secret }}</p>
    @endif

    @if ($confirmed)
        <div class="l2fa-field">
            <input type="checkbox" id="l2fa-regenerate-{{ $guard }}" name="two_factor_regenerate_codes" value="1">
            <label for="l2fa-regenerate-{{ $guard }}">{{ trans('laravel-2fa::laravel-2fa.generate_new_recovery_codes') }}</label>
        </div>

        @if ($downloadUrl)
            <a class="l2fa-link" href="{{ $downloadUrl }}">{{ trans('laravel-2fa::laravel-2fa.download_recovery_codes') }}</a>
        @endif
    @endif

    @if (!empty($recoveryCodes))
        <p class="l2fa-text">{{ trans('laravel-2fa::laravel-2fa.store_recovery_codes') }}</p>
        <ul class="l2fa-codes">
            @foreach ($recoveryCodes as $recoveryCode)
                <li>{{ $recoveryCode }}</li>
            @endforeach
        </ul>
    @endif
</div>
