<?php

declare(strict_types=1);

namespace Made\CmsPackage\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Made\CmsPackage\CmsPackage
 */
class CmsPackage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Made\CmsPackage\CmsPackage::class;
    }
}
