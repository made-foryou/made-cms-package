<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Exceptions;

use Made\CmsPackage\Content\Strips\StripType;
use RuntimeException;

/**
 * The site has a strip type without the view that renders it; a mistake of the developer, not of an editor.
 */
final class MissingStripView extends RuntimeException
{
    public static function for(StripType $type, string $view): self
    {
        $hint = str_contains($view, '::')
            ? 'create that view'
            : 'add resources/views/'.str_replace('.', '/', $view).'.blade.php';

        return new self(sprintf(
            'Strip type [%s] (%s) has no view [%s]; %s or return another view from view().',
            $type->key(),
            $type::class,
            $view,
            $hint,
        ));
    }
}
