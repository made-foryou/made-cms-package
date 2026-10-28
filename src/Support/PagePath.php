<?php

declare(strict_types=1);

namespace Made\CmsPackage\Support;

/**
 * The path of a page of the site, written one way: a leading slash, no trailing slash, "/" for the home page.
 */
final class PagePath
{
    /**
     * "lid-worden", "/lid-worden/" and "/lid-worden" all become "/lid-worden", so $request->path() finds the page.
     */
    public static function normalise(string $path): string
    {
        return '/'.trim($path, '/');
    }
}
