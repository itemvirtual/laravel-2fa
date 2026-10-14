<?php

namespace Itemvirtual\Laravel2fa;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Itemvirtual\Laravel2fa\Skeleton\SkeletonClass
 */
class Laravel2faFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-2fa';
    }
}
