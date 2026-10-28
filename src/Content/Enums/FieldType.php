<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * What an editor fills in for a field of a strip. A new type also needs a branch in ParseStripType and
 * ValidateStripContent and its value in the enum of resources/schema/strip.json.
 */
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Image = 'image';
    case Link = 'link';
    case List = 'list';
}
