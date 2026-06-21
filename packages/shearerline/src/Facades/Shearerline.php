<?php

namespace Shearerline\Facades;

use Illuminate\Support\Facades\Facade;

class Shearerline extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'shearerline';
    }
}
