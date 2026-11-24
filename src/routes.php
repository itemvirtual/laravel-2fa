<?php

use Illuminate\Support\Facades\Route;
use Itemvirtual\Laravel2fa\Http\Controllers\ChallengeController;
use Itemvirtual\Laravel2fa\Http\Controllers\RecoveryCodesController;
use Itemvirtual\Laravel2fa\Laravel2faFacade as Laravel2fa;

foreach (Laravel2fa::guards() as $guard) {
    $throttle = function (int $max, string $name) use ($guard) {
        return 'throttle:' . $max . ',1,laravel-2fa.' . $guard . '.' . $name;
    };

    Route::middleware(Laravel2fa::guardConfig($guard, 'middleware', ['web']))
        ->prefix(Laravel2fa::guardConfig($guard, 'prefix', 'two-factor'))
        ->name('laravel-2fa.' . $guard . '.')
        ->group(function () use ($guard, $throttle) {
            Route::middleware('guest:' . $guard)->group(function () use ($guard, $throttle) {
                Route::get('challenge', [ChallengeController::class, 'show'])
                    ->name('challenge')
                    ->defaults('guard', $guard);
                Route::post('challenge', [ChallengeController::class, 'store'])
                    ->middleware($throttle(6, 'challenge'))
                    ->name('challenge.store')
                    ->defaults('guard', $guard);
                Route::post('challenge/resend', [ChallengeController::class, 'resend'])
                    ->middleware($throttle(3, 'challenge.resend'))
                    ->name('challenge.resend')
                    ->defaults('guard', $guard);
            });

            Route::middleware('auth:' . $guard)->group(function () use ($guard) {
                Route::get('recovery-codes/download', [RecoveryCodesController::class, 'download'])
                    ->name('recovery-codes.download')
                    ->defaults('guard', $guard);
            });
        });
}
