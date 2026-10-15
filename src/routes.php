<?php

use Illuminate\Support\Facades\Route;
use Itemvirtual\Laravel2fa\Http\Controllers\ChallengeController;
use Itemvirtual\Laravel2fa\Http\Controllers\SetupController;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;

foreach (Laravel2fa::guards() as $guard) {
    Route::middleware(Laravel2fa::guardConfig($guard, 'middleware', ['web']))
        ->prefix(Laravel2fa::guardConfig($guard, 'prefix', 'two-factor'))
        ->name('laravel-2fa.' . $guard . '.')
        ->group(function () use ($guard) {
            Route::middleware('guest:' . $guard)->group(function () use ($guard) {
                Route::get('challenge', [ChallengeController::class, 'show'])
                    ->name('challenge')
                    ->defaults('guard', $guard);
                Route::post('challenge', [ChallengeController::class, 'store'])
                    ->middleware('throttle:6,1')
                    ->name('challenge.store')
                    ->defaults('guard', $guard);
            });

            Route::middleware('auth:' . $guard)->group(function () use ($guard) {
                Route::get('setup', [SetupController::class, 'show'])
                    ->name('setup')
                    ->defaults('guard', $guard);
                Route::post('setup', [SetupController::class, 'store'])
                    ->middleware('throttle:6,1')
                    ->name('setup.store')
                    ->defaults('guard', $guard);
                Route::delete('setup', [SetupController::class, 'destroy'])
                    ->middleware('throttle:6,1')
                    ->name('setup.destroy')
                    ->defaults('guard', $guard);
                Route::post('recovery-codes', [SetupController::class, 'recoveryCodes'])
                    ->middleware('throttle:6,1')
                    ->name('recovery-codes')
                    ->defaults('guard', $guard);
            });
        });
}
