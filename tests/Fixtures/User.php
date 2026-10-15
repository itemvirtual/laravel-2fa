<?php

namespace Itemvirtual\Laravel2fa\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Itemvirtual\Laravel2fa\Contracts\TwoFactorAuthenticatable;
use Itemvirtual\Laravel2fa\Traits\TwoFactorAuthentication;

class User extends Authenticatable implements TwoFactorAuthenticatable
{
    use TwoFactorAuthentication;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}
