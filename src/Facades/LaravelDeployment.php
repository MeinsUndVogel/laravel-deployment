<?php

namespace muv\LaravelDeployment\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \muv\LaravelDeployment\LaravelDeployment
 */
class LaravelDeployment extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \muv\LaravelDeployment\LaravelDeployment::class;
    }
}
