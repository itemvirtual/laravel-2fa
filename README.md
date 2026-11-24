# Laravel 2FA (two-factor authentication)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/itemvirtual/laravel-2fa.svg?style=flat-square)](https://packagist.org/packages/itemvirtual/laravel-2fa)
[![Total Downloads](https://img.shields.io/packagist/dt/itemvirtual/laravel-2fa.svg?style=flat-square)](https://packagist.org/packages/itemvirtual/laravel-2fa)

Two-factor authentication (authenticator app or email code) for Laravel projects using Breeze (or any session based
login), with support for multiple independent guards, each one with its own table, provider, session state, routes
and views.

- Works with Laravel 9, 10, 11, 12 and 13 (PHP 8.1+, PHP 8.3+ for Laravel 13)
- Any authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Authy...) or a code sent by email
- Single use recovery codes
- Each user turns 2FA on or off and chooses the method from their profile form
- Database columns added with a Laravel migration or with Doctrine xml-mappings

## Table of contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Database](#database)
- [Model setup](#model-setup)
- [Who uses 2FA](#who-uses-2fa)
- [Email method](#email-method)
- [Login integration (Breeze)](#login-integration-breeze)
- [Logout per guard](#logout-per-guard)
- [Routes](#routes)
- [Views](#views)
- [Translations](#translations)
- [Security notes](#security-notes)
- [Testing](#testing)

## Installation

Install the package via composer:

```bash
composer require itemvirtual/laravel-2fa
```

The service provider and the `Laravel2fa` facade are registered through package auto-discovery.

### Publishing

```bash
# Configuration file: config/laravel-2fa.php
php artisan vendor:publish --provider="Itemvirtual\Laravel2fa\Laravel2faServiceProvider" --tag=config

# Views: resources/views/laravel-2fa (see views_path)
php artisan vendor:publish --provider="Itemvirtual\Laravel2fa\Laravel2faServiceProvider" --tag=views

# Migration: database/migrations/{date}_add_two_factor_columns.php
php artisan vendor:publish --provider="Itemvirtual\Laravel2fa\Laravel2faServiceProvider" --tag=migrations

# Doctrine fields snippet: database/doctrine/laravel-2fa-fields.xml
php artisan vendor:publish --provider="Itemvirtual\Laravel2fa\Laravel2faServiceProvider" --tag=doctrine
```

## Configuration

### Environment variables

| Variable                         | Default    | Description                                                |
|----------------------------------|------------|------------------------------------------------------------|
| `LARAVEL_2FA_ISSUER`             | `APP_NAME` | Name shown in the authenticator app                        |
| `LARAVEL_2FA_WINDOW`             | `1`        | Accepted drift, in 30 second periods, before and after now |
| `LARAVEL_2FA_RECOVERY_CODES`     | `8`        | Number of recovery codes generated for each user           |
| `LARAVEL_2FA_EMAIL_EXPIRES`      | `10`       | Minutes before an email code expires                       |
| `LARAVEL_2FA_EMAIL_LENGTH`       | `6`        | Digits of the email code                                   |
| `LARAVEL_2FA_EMAIL_MAX_ATTEMPTS` | `5`        | Wrong attempts before an email code is discarded           |

### Reference

| Key                 | Description                                                                          |
|---------------------|--------------------------------------------------------------------------------------|
| `issuer`            | Name shown in the authenticator app                                                  |
| `window`            | Accepted drift, in 30 second periods                                                 |
| `recovery_codes`    | Number of recovery codes                                                             |
| `email`             | Email method options: `expires`, `length`, `max_attempts`                            |
| `guards`            | One entry per auth guard, see below                                                  |
| `views_path`        | Directory inside `resources/views` where views are published (default `laravel-2fa`) |
| `xml_mappings_path` | Doctrine xml-mappings directory used by `laravel-2fa:doctrine-mappings`              |
| `save_logs`         | Log the changes made by `laravel-2fa:doctrine-mappings`                              |
| `logging`           | Log channel used when `save_logs` is enabled                                         |

Options of each guard (`guards.{name}`):

| Key               | Description                                                             |
|-------------------|-------------------------------------------------------------------------|
| `methods`         | Methods the user can choose: `totp`, `email` (default both)             |
| `prefix`          | URL prefix of the routes (e.g. `two-factor`, `<your-guard>/two-factor`) |
| `middleware`      | Middleware applied to all the routes of the guard (usually `['web']`)   |
| `login_route`     | Route name of the guard login, used when there is no pending challenge  |
| `redirect`        | URL after a successful challenge (when there is no intended URL)        |
| `views.challenge` | View of the challenge form                                              |
| `views.fields`    | View rendered by `formFields()` (default `laravel-2fa::fields`)         |

### Adding a guard

The package only ships the `web` guard. Add any other guard in the published `config/laravel-2fa.php`.

Note that the published `guards` array **replaces** the package default (config merging is not recursive), so keep
the `web` entry if you need it:

```php
'guards' => [

    'web' => [
        'methods' => ['totp', 'email'],
        'prefix' => 'two-factor',
        'middleware' => ['web'],
        'login_route' => 'login',
        'redirect' => '/dashboard',
        'views' => [
            'challenge' => 'laravel-2fa::challenge',
            'fields' => 'laravel-2fa::fields',
        ],
    ],

    '<your-guard>' => [
        'methods' => ['totp'],
        'prefix' => '<your-guard>/two-factor',
        'middleware' => ['web'],
        'login_route' => '<your-guard>.login',
        'redirect' => '/<your-guard>/dashboard',
        'views' => [
            'challenge' => 'laravel-2fa::challenge',
            'fields' => 'laravel-2fa::fields',
        ],
    ],

],
```

The guard name must exist in `config/auth.php`. The table of each guard is resolved through its provider (guard -> provider -> model -> table).

## Database

Every guard table gets these columns:

| Column                      | Type                           | Description                                    |
|-----------------------------|--------------------------------|------------------------------------------------|
| `two_factor_enabled`        | boolean, default `false`       | Whether the user uses 2FA (managed by the app) |
| `two_factor_method`         | string(10), nullable           | Chosen method: `totp` or `email`               |
| `two_factor_secret`         | text, nullable                 | Encrypted TOTP secret                          |
| `two_factor_recovery_codes` | text, nullable                 | Encrypted recovery codes                       |
| `two_factor_last_timestamp` | unsigned big integer, nullable | Last accepted code timestamp (replay guard)    |
| `two_factor_confirmed_at`   | timestamp, nullable            | When the method was made ready                 |

Configure the guards before adding the columns: both options read the tables from `laravel-2fa.guards`.

### Option 1: migration

```bash
php artisan vendor:publish --provider="Itemvirtual\Laravel2fa\Laravel2faServiceProvider" --tag=migrations
php artisan migrate
```

The migration adds the missing columns to the table of every configured guard and skips the ones that already
exist, so it can be run on tables that are partially set up.

### Option 2: Doctrine xml-mappings

For projects that keep the database in sync with Doctrine xml-mappings (e.g. with
[itemvirtual/laravel-doctrine](https://github.com/itemvirtual/laravel-doctrine)) instead of migrations.

The command adds the fields to the existing mapping of every guard table, located in `xml_mappings_path`
(default `database/doctrine/xml-mappings`):

```bash
# Preview the changes
php artisan laravel-2fa:doctrine-mappings --dry-run

# Write the fields into the mappings
php artisan laravel-2fa:doctrine-mappings

# Update the database
php artisan doctrine:update
```

- It never touches the database, only the `.dcm.xml` files
- Fields that already exist are skipped, so it can be run more than once
- Before writing anything it checks that the path exists and that every guard table has a mapping; otherwise it
  exits with code 1 and no file is modified. Mappings of other tables are ignored

To add the fields by hand, publish the snippet (`--tag=doctrine`) and paste it inside the `<entity>` element of each
guard mapping:

```xml

<field name="twoFactorEnabled" type="boolean" column="two_factor_enabled" nullable="false">
    <options>
        <option name="default">0</option>
    </options>
</field>
<field name="twoFactorMethod" type="string" column="two_factor_method" length="10" nullable="true"/>
<field name="twoFactorSecret" type="text" column="two_factor_secret" nullable="true"/>
<field name="twoFactorRecoveryCodes" type="text" column="two_factor_recovery_codes" nullable="true"/>
<field name="twoFactorLastTimestamp" type="bigint" column="two_factor_last_timestamp" nullable="true">
<options>
    <option name="unsigned"/>
</options>
</field>
<field name="twoFactorConfirmedAt" type="datetime" column="two_factor_confirmed_at" nullable="true"/>
```

## Model setup

Each guard model implements the contract and uses the trait:

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable;
use Itemvirtual\Laravel2fa\Traits\TwoFactorAuthentication;

class User extends Authenticatable implements TwoFactorAuthenticatable
{
    use TwoFactorAuthentication;
}
```

The trait adds the casts (`boolean`, `encrypted`, `encrypted:array`, `integer`, `datetime`) and hides the secret,
the recovery codes and the last timestamp from arrays and JSON.

Do not add the `two_factor_*` columns to `$fillable`: only `updateSettings()` must change them. If they are fillable,
`$user->update($request->all())` writes `two_factor_enabled` and `two_factor_method` before `updateSettings()` runs,
and a change of method is not applied.

Example `config/auth.php` with two guards:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    '<your-guard>' => [
        'driver' => 'session',
        'provider' => '<your-provider>',
    ],
],

'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Models\User::class,
    ],
    '<your-provider>' => [
        'driver' => 'eloquent',
        'model' => App\Models\YourModel::class,
    ],
],
```

## Who uses 2FA

Each user decides it from their profile form: turn 2FA on or off, choose the method and regenerate the recovery codes.
The login asks for the code only when `two_factor_enabled` is `true` and the method is ready.

### From the user profile form

When the form is saved, `updateSettings()` leaves everything ready for the chosen method (the TOTP secret and the
recovery codes) with no setup step: the next login already asks for the code.

Render the fields inside the form with `formFields()`:

```blade
<form method="POST" action="{{ route('profile.update') }}">
    @csrf
    @method('PATCH')

    {{ Laravel2fa::formFields('web', $user) }}

    <button type="submit">Save</button>
</form>
```

| Field                         | Shown                       | Description                             |
|-------------------------------|-----------------------------|-----------------------------------------|
| `two_factor_enabled`          | always                      | Checkbox; not sent when unchecked       |
| `two_factor_method`           | always                      | Methods available to the user           |
| `two_factor_regenerate_codes` | when 2FA is enabled         | Checkbox to generate new recovery codes |

The fields keep the submitted values with `old()` after a validation error, including an unchecked checkbox. The new
recovery codes are shown only once, the next time the fields are rendered.

An unchecked checkbox is not sent, so `$request->boolean('two_factor_enabled')` is `false` both when the user
unchecks it and when the fields are not in the form. If the form can also edit other users, render the fields and call
`updateSettings()` only when editing the authenticated user, otherwise saving another user disables their 2FA:

```blade
@if (Auth::guard('web')->user()->is($user))
    {{ Laravel2fa::formFields('web', $user) }}
@endif
```

```php
if (Auth::guard('web')->user()->is($user)) {
    Laravel2fa::updateSettings($request, 'web', $user, $request->boolean('two_factor_enabled'), $request->input('two_factor_method'));
}
```

When 2FA is enabled and the edited user is the authenticated one, the fields also show:

- a link to download the recovery codes as a `.txt` file (the download route only returns the codes of the
  authenticated user)
- with the `totp` method, the QR code and the setup key to add the account to the authenticator application

Neither is shown when editing another user.

```php
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;

public function update(Request $request): RedirectResponse
{
    $user = $request->user();

    $request->validate([
        'two_factor_enabled' => ['boolean'],
        'two_factor_method' => ['required_if:two_factor_enabled,1', 'nullable', 'in:' . implode(',', Laravel2fa::availableMethods('web', $user))],
    ]);

    Laravel2fa::updateSettings($request, 'web', $user, $request->boolean('two_factor_enabled'), $request->input('two_factor_method'));

    if ($request->boolean('two_factor_regenerate_codes')) {
        Laravel2fa::regenerateRecoveryCodes($request, $user);
    }

    return back();
}
```

| Input                              | Result                                                                | Next step                                       |
|------------------------------------|-----------------------------------------------------------------------|-------------------------------------------------|
| `enabled = false`                  | 2FA disabled; method, secret and recovery codes cleared               | None                                            |
| `enabled = true`, `method = totp`  | 2FA ready: new secret and recovery codes                              | Scan the QR code shown in the form              |
| `enabled = true`, `method = email` | 2FA ready: new recovery codes; no email is sent                       | None, a code is sent by email on the next login |
| `enabled = true`, same method      | Nothing changes                                                       | None                                            |
| `enabled = true`, other method     | 2FA ready with the new method: new secret (`totp`) and recovery codes | Same as the chosen method                       |

- Validate the method before calling it: an empty or not allowed method throws `InvalidArgumentException`
- With `totp`, scan the QR code before logging out: the next login asks for the code of the authenticator application
- Disabling clears everything, so enabling it again generates a new secret and new recovery codes
- The recovery codes are not shown when they are generated; the user gets them by downloading or regenerating them
  from the form
- `regenerateRecoveryCodes()` does nothing for users without 2FA ready
- Ask for the current password in the profile form: `updateSettings()` and `regenerateRecoveryCodes()` do not require
  a 2FA code

## Email method

When a guard allows `email`, the user can choose it in the profile form instead of the authenticator app. A code is
sent by email on every login.

- The project must have a working mail configuration (`MAIL_*` in `.env`)
- The address is read from the `email` attribute; override `getTwoFactorEmail()` in the model to use another one
- Users without an email address can only choose `totp`
- The email view is `laravel-2fa::mail.code` (published with the other views)
- Recovery codes work for both methods

```php
public function getTwoFactorEmail(): ?string
{
    return $this->notification_email ?: $this->email;
}
```

## Login integration (Breeze)

Add one call after `$request->authenticate()` in the login controller of each guard. When the user needs the
challenge, the guard is logged out again, the pending login is stored in the session and the user is redirected to
the challenge page:

```php
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;

public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();

    if ($redirect = Laravel2fa::challengeIfRequired($request, 'web')) {
        return $redirect;
    }

    $request->session()->regenerate();

    return redirect()->intended(route('dashboard', absolute: false));
}
```

Use the guard name (`'<your-guard>'`) in the login controller of each other guard. The "remember me" checkbox (`remember` input) is
kept and applied when the challenge is completed.

## Logout per guard

Breeze logs out with `session()->invalidate()`, which destroys the whole session and therefore closes **all**
guards. To log out only one guard, replace it with:

```php
public function destroy(Request $request): RedirectResponse
{
    Auth::guard('<your-guard>')->logout();

    $request->session()->regenerate();
    $request->session()->regenerateToken();

    return redirect('/<your-guard>/login');
}
```

## Routes

Registered for every guard listed in `guards`, with the guard `prefix` and `middleware`. Example for `web` (prefix
`two-factor`); any other guard gets `laravel-2fa.<your-guard>.*` names:

| Method | URI                                  | Name                                      | Middleware            |
|--------|--------------------------------------|-------------------------------------------|-----------------------|
| GET    | `two-factor/challenge`               | `laravel-2fa.web.challenge`               | `guest:web`           |
| POST   | `two-factor/challenge`               | `laravel-2fa.web.challenge.store`         | `guest:web`, throttle |
| POST   | `two-factor/challenge/resend`        | `laravel-2fa.web.challenge.resend`        | `guest:web`, throttle |
| GET    | `two-factor/recovery-codes/download` | `laravel-2fa.web.recovery-codes.download` | `auth:web`            |

- `recovery-codes/download`: downloads the current recovery codes of the authenticated user as `recovery-codes.txt`,
  one code per line. Users without 2FA are redirected to the guard `redirect`
- Guards not listed in `guards` have no routes and no challenge

## Views

The package ships standalone views (`laravel-2fa::layout`, `laravel-2fa::challenge`) with
inline CSS and no external assets, and the `laravel-2fa::fields` partial for the profile form, without styles: publish
it to add the classes of your project (e.g. Bootstrap).

Publishing them copies them to `resources/views/{views_path}` (default `resources/views/laravel-2fa`). Views found
there override the package ones and the missing ones fall back to the package, so you can publish all of them and
change only `layout.blade.php`.

Each guard can also use completely different views, for example one with the Breeze layout and another one with a
different layout:

```php
'views' => [
    'challenge' => 'auth.two-factor-challenge',
    'fields' => 'profile.two-factor-fields',
],
```

Variables available in the challenge view:

| Variable        | Description                                              |
|-----------------|----------------------------------------------------------|
| `$guard`        | Guard name                                               |
| `$action`       | URL of the form (`POST`); send `code` or `recovery_code` |
| `$method`       | `totp` or `email`                                        |
| `$email`        | Masked email address (e.g. `j***@example.com`)           |
| `$resendAction` | URL to send a new email code (`POST`)                    |

Errors are returned in `code` or `recovery_code`.

Variables available in the fields view:

| Variable         | Description                                                  |
|------------------|--------------------------------------------------------------|
| `$guard`         | Guard name                                                   |
| `$enabled`       | Whether 2FA is enabled                                       |
| `$confirmed`     | Whether the method is ready                                  |
| `$method`        | `totp`, `email` or `null`                                    |
| `$methods`       | Methods available to the user                                |
| `$recoveryCodes` | New recovery codes, only right after they are regenerated    |
| `$downloadUrl`   | Download URL of the codes, `null` when editing another user  |
| `$qrCode`        | QR code SVG with `totp`, `null` when editing another user    |
| `$secret`        | Setup key with `totp`, `null` when editing another user      |

### Styling the fields (Bootstrap 5)

The `fields` view has no styles. Publish the views and replace `resources/views/laravel-2fa/fields.blade.php` with
your own markup. Example for a Bootstrap 5 form; keep the field names (`two_factor_enabled`, `two_factor_method`,
`two_factor_regenerate_codes`) and the `old()` default of the checkbox:

```blade
<h2 class="h5 mb-3">{{ trans('laravel-2fa::laravel-2fa.two_factor_authentication') }}</h2>

<div class="row g-3 align-items-end justify-content-between">
    <div class="col-12 col-lg-6">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="l2fa-enabled-{{ $guard }}" name="two_factor_enabled" value="1" @checked(old('two_factor_enabled', session()->hasOldInput() ? false : $enabled))>
            <label class="form-check-label" for="l2fa-enabled-{{ $guard }}">{{ trans('laravel-2fa::laravel-2fa.enable_two_factor') }}</label>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <label class="form-label" for="l2fa-method-{{ $guard }}">{{ trans('laravel-2fa::laravel-2fa.method') }}</label>
        <select class="form-select" id="l2fa-method-{{ $guard }}" name="two_factor_method">
            @foreach ($methods as $option)
                <option value="{{ $option }}" @selected(old('two_factor_method', $method) === $option)>{{ $option === 'email' ? trans('laravel-2fa::laravel-2fa.method_email') : trans('laravel-2fa::laravel-2fa.method_totp') }}</option>
            @endforeach
        </select>
    </div>
</div>

@if ($qrCode)
    <div class="d-flex flex-wrap align-items-center gap-3 border-top pt-3 mt-3">
        <div class="bg-white p-2 border rounded">{!! $qrCode !!}</div>
        <div>
            <p class="small mb-2">{{ trans('laravel-2fa::laravel-2fa.fields_totp_text') }}</p>
            <code class="user-select-all">{{ $secret }}</code>
        </div>
    </div>
@endif

@if ($confirmed)
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border-top pt-3 mt-3">
        <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="l2fa-regenerate-{{ $guard }}" name="two_factor_regenerate_codes" value="1">
            <label class="form-check-label" for="l2fa-regenerate-{{ $guard }}">{{ trans('laravel-2fa::laravel-2fa.generate_new_recovery_codes') }}</label>
        </div>

        @if ($downloadUrl)
            <a class="btn btn-sm btn-outline-secondary" href="{{ $downloadUrl }}">{{ trans('laravel-2fa::laravel-2fa.download_recovery_codes') }}</a>
        @endif
    </div>
@endif

@if (!empty($recoveryCodes))
    <div class="alert alert-info mt-3 mb-0">
        <p class="small mb-2">{{ trans('laravel-2fa::laravel-2fa.store_recovery_codes') }}</p>
        <ul class="list-unstyled row row-cols-2 row-cols-md-4 g-1 font-monospace small mb-0">
            @foreach ($recoveryCodes as $recoveryCode)
                <li class="col">{{ $recoveryCode }}</li>
            @endforeach
        </ul>
    </div>
@endif
```

## Translations

All texts use `trans('laravel-2fa::laravel-2fa.{key}')`. The package ships English (`en`), Spanish (`es`) and Catalan
(`ca`) in `lang/{locale}/laravel-2fa.php`. To override or add a language, create
`lang/vendor/laravel-2fa/{locale}/laravel-2fa.php` in the project with the keys you want to change.

## Security notes

- **Encrypted storage**: the secret and the recovery codes are encrypted with the application key. Changing
  `APP_KEY` makes them unreadable and every user has to enable 2FA again from the profile form
- **Replay protection**: each code is accepted only once; the timestamp of the last accepted code is stored and
  older or equal codes are rejected
- **Throttling**: the challenge (`POST`) is limited to 6 requests per minute and the email resend to 3, each route
  with its own counter
- **Email codes**: only an HMAC of the code is kept in the session, never the code itself; it expires after
  `email.expires` minutes, is single use and is discarded after `email.max_attempts` wrong attempts
- **Recovery codes**: single use and compared with `hash_equals`. They are shown only once, right after they are
  regenerated from the form, and the authenticated user can download them as `.txt`
- **Remember me**: the choice is kept during the challenge and the remember cookie is only created after the code is
  verified. When a login is challenged, the guard is logged out with Laravel's `logout()`, which fires the `Logout`
  event and rotates the user remember token (other devices remembered for that user have to log in again)
- **Isolation**: the pending login is stored per guard (`laravel-2fa.{guard}.pending`), so a challenge on one guard
  never affects the session of another guard

## Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Credits

- [Sergio](https://github.com/sergio-item)
- [Itemvirtual](https://github.com/itemvirtual)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
