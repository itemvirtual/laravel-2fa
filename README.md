# Laravel 2FA (two-factor authentication)

[![Latest Version on Packagist](https://img.shields.io/packagist/v/itemvirtual/laravel-2fa.svg?style=flat-square)](https://packagist.org/packages/itemvirtual/laravel-2fa)
[![Total Downloads](https://img.shields.io/packagist/dt/itemvirtual/laravel-2fa.svg?style=flat-square)](https://packagist.org/packages/itemvirtual/laravel-2fa)

TOTP two-factor authentication for Laravel projects using Breeze (or any session based login), with support for
multiple independent guards, each one with its own table, provider, session state, routes
and views.

- Works with Laravel 9, 10, 11 and 12 (PHP 8.1+)
- Any authenticator app (Google Authenticator, Microsoft Authenticator, 1Password, Authy...)
- Single use recovery codes
- 2FA is used only by the users whose `two_factor_enabled` column is `true`; the project decides who
- Database columns added with a Laravel migration or with Doctrine xml-mappings

## Table of contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Database](#database)
- [Model setup](#model-setup)
- [Who uses 2FA](#who-uses-2fa)
- [Login integration (Breeze)](#login-integration-breeze)
- [Logout per guard](#logout-per-guard)
- [Routes](#routes)
- [Middleware](#middleware)
- [Views](#views)
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

| Variable                     | Default    | Description                                                |
|------------------------------|------------|------------------------------------------------------------|
| `LARAVEL_2FA_ISSUER`         | `APP_NAME` | Name shown in the authenticator app                        |
| `LARAVEL_2FA_WINDOW`         | `1`        | Accepted drift, in 30 second periods, before and after now |
| `LARAVEL_2FA_RECOVERY_CODES` | `8`        | Number of recovery codes generated for each user           |

### Reference

| Key                 | Description                                                                          |
|---------------------|--------------------------------------------------------------------------------------|
| `issuer`            | Name shown in the authenticator app                                                  |
| `window`            | Accepted drift, in 30 second periods                                                 |
| `recovery_codes`    | Number of recovery codes                                                             |
| `guards`            | One entry per auth guard, see below                                                  |
| `views_path`        | Directory inside `resources/views` where views are published (default `laravel-2fa`) |
| `xml_mappings_path` | Doctrine xml-mappings directory used by `laravel-2fa:doctrine-mappings`              |
| `save_logs`         | Log the changes made by `laravel-2fa:doctrine-mappings`                              |
| `logging`           | Log channel used when `save_logs` is enabled                                         |

Options of each guard (`guards.{name}`):

| Key               | Description                                                             |
|-------------------|-------------------------------------------------------------------------|
| `prefix`          | URL prefix of the routes (e.g. `two-factor`, `<your-guard>/two-factor`) |
| `middleware`      | Middleware applied to all the routes of the guard (usually `['web']`)   |
| `login_route`     | Route name of the guard login, used when there is no pending challenge  |
| `redirect`        | URL after a successful challenge (when there is no intended URL)        |
| `views.challenge` | View of the challenge form                                              |
| `views.setup`     | View of the setup page                                                  |

### Adding a guard

The package only ships the `web` guard. Add any other guard in the published `config/laravel-2fa.php`.

Note that the published `guards` array **replaces** the package default (config merging is not recursive), so keep
the `web` entry if you need it:

```php
'guards' => [

    'web' => [
        'prefix' => 'two-factor',
        'middleware' => ['web'],
        'login_route' => 'login',
        'redirect' => '/dashboard',
        'views' => [
            'challenge' => 'laravel-2fa::challenge',
            'setup' => 'laravel-2fa::setup',
        ],
    ],

    '<your-guard>' => [
        'prefix' => '<your-guard>/two-factor',
        'middleware' => ['web'],
        'login_route' => '<your-guard>.login',
        'redirect' => '/<your-guard>/dashboard',
        'views' => [
            'challenge' => 'laravel-2fa::challenge',
            'setup' => 'laravel-2fa::setup',
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
| `two_factor_secret`         | text, nullable                 | Encrypted TOTP secret                          |
| `two_factor_recovery_codes` | text, nullable                 | Encrypted recovery codes                       |
| `two_factor_last_timestamp` | unsigned big integer, nullable | Last accepted code timestamp (replay guard)    |
| `two_factor_confirmed_at`   | timestamp, nullable            | When the setup was confirmed                   |

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

The `two_factor_enabled` column decides it. The package only reads it; turning it on or off is up to the project (a back office, a profile option, a seeder...):

```php
$user->forceFill(['two_factor_enabled' => true])->save();
```

| `two_factor_enabled` | 2FA set up | Login             | `laravel-2fa.enforced` middleware |
|----------------------|------------|-------------------|-----------------------------------|
| `false`              | any        | normal            | passes                            |
| `true`               | no         | normal            | redirects to the setup page       |
| `true`               | yes        | asks for the code | passes                            |

Setting the flag to `false` keeps the secret, so turning it on again does not require a new setup.

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

| Method | URI                         | Name                              | Middleware            |
|--------|-----------------------------|-----------------------------------|-----------------------|
| GET    | `two-factor/challenge`      | `laravel-2fa.web.challenge`       | `guest:web`           |
| POST   | `two-factor/challenge`      | `laravel-2fa.web.challenge.store` | `guest:web`, throttle |
| GET    | `two-factor/setup`          | `laravel-2fa.web.setup`           | `auth:web`            |
| POST   | `two-factor/setup`          | `laravel-2fa.web.setup.store`     | `auth:web`, throttle  |
| DELETE | `two-factor/setup`          | `laravel-2fa.web.setup.destroy`   | `auth:web`, throttle  |
| POST   | `two-factor/recovery-codes` | `laravel-2fa.web.recovery-codes`  | `auth:web`, throttle  |

- `setup`: shows the QR code and confirms the setup, or, once confirmed, regenerates the recovery codes and resets
  the setup (`DELETE`, e.g. after changing the device). Both actions require a valid code
- Users with `two_factor_enabled = false` are redirected to the guard `redirect` from any setup route
- Guards not listed in `guards` have no routes and no challenge

Link to the setup page from the profile or any menu:

```blade
<a href="{{ route('laravel-2fa.<your-guard>.setup') }}">Two factor authentication</a>
```

## Middleware

`laravel-2fa.enforced:{guard}` redirects the users with `two_factor_enabled = true` that have not set up 2FA yet to
the setup page. The alias is registered by the package, no changes are needed in the HTTP kernel or in
`bootstrap/app.php`:

```php
Route::middleware(['auth:<your-guard>', 'laravel-2fa.enforced:<your-guard>'])->group(function () {
    // protected routes
});
```

The package routes are always allowed. Keep the logout route **outside** this middleware, otherwise a user that has
to set up 2FA cannot log out.

## Views

The package ships standalone views (`laravel-2fa::layout`, `laravel-2fa::challenge`, `laravel-2fa::setup`) with
inline CSS and no external assets.

Publishing them copies them to `resources/views/{views_path}` (default `resources/views/laravel-2fa`). Views found
there override the package ones and the missing ones fall back to the package, so you can publish all of them and
change only `layout.blade.php`.

Each guard can also use completely different views, for example one with the Breeze layout and another one with a
different layout:

```php
'views' => [
    'challenge' => 'auth.two-factor-challenge',
    'setup' => 'profile.two-factor',
],
```

Variables available in the challenge view:

| Variable  | Description                                              |
|-----------|----------------------------------------------------------|
| `$guard`  | Guard name                                               |
| `$action` | URL of the form (`POST`); send `code` or `recovery_code` |

Errors are returned in `code` or `recovery_code`.

Variables available in the setup view:

| Variable                   | Description                                                  |
|----------------------------|--------------------------------------------------------------|
| `$guard`                   | Guard name                                                   |
| `$user`                    | Authenticated user                                           |
| `$confirmed`               | Whether the setup is confirmed                               |
| `$secret`                  | Setup key, only while not confirmed                          |
| `$qrCode`                  | QR code SVG, only while not confirmed (print with `{!! !!}`) |
| `$recoveryCodes`           | New recovery codes, only right after they are generated      |
| `$redirect`                | Guard `redirect` URL                                         |
| `$routes['confirm']`       | `POST` with `code`: confirm the setup                        |
| `$routes['recoveryCodes']` | `POST` with `code`: regenerate the recovery codes            |
| `$routes['reset']`         | `DELETE` with `code`: reset the setup                        |

`session('laravel-2fa.status')` is `two-factor-confirmed`, `recovery-codes-generated` or `two-factor-reset` after
each action. Errors are returned in `code`.

All texts use `__()`, so they can be translated in the project JSON language files.

## Security notes

- **Encrypted storage**: the secret and the recovery codes are encrypted with the application key. Changing
  `APP_KEY` makes them unreadable and every user has to set up 2FA again
- **Replay protection**: each code is accepted only once; the timestamp of the last accepted code is stored and
  older or equal codes are rejected
- **Throttling**: all the `POST`/`DELETE` routes are limited to 6 requests per minute
- **Recovery codes**: single use, compared with `hash_equals`, and shown only once right after they are generated
- **Sensitive actions**: regenerating the recovery codes and resetting the setup require a valid code
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
