<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * Why the scan wrote no class for a strip type. It never overwrites: what the site has stays the developer's.
 */
enum StripTypeSkip: string
{
    /** The catalogue would refuse the definition, and with it every page of the site. */
    case Invalid = 'invalid';

    /** The catalogue has a strip type with this key, or the scan has it twice. */
    case KeyExists = 'key_exists';

    /** The folder has a class with this name, whatever its capitals, or the scan gives two types the same name. */
    case ClassExists = 'class_exists';

    public function label(): string
    {
        return (string) __('made-cms-package::scan.generate.skipped.'.$this->value);
    }
}
