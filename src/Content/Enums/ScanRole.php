<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Enums;

/**
 * What an element of a scanned page is, as far as the markup tells: the scan names a field after its role and
 * proposes the field type of the role.
 */
enum ScanRole: string
{
    /** A heading, h1 to h6. */
    case Heading = 'heading';

    /** A short text in any other element. */
    case Text = 'text';

    /** A paragraph. */
    case Paragraph = 'paragraph';

    /** Several paragraphs or a list of plain lines, or a paragraph with a link. */
    case RichText = 'rich_text';

    /** A repeat inside an item of a list: one line per element. */
    case List = 'list';

    /** A link with a text of its own. */
    case Button = 'button';

    /** A link without a text of its own, or around more than text. */
    case Link = 'link';

    /** An img, a picture or an inline background image. */
    case Image = 'image';

    /** An icon inside an item of a list: a choice when the icons of the items differ. */
    case Icon = 'icon';

    /** Two or more siblings with the same structure. */
    case Items = 'items';

    /**
     * The type of the strip field the role proposes, as Field::type() names it; the image field is still to come.
     */
    public function type(): string
    {
        return match ($this) {
            self::Heading, self::Text => 'text',
            self::Paragraph, self::List => 'long_text',
            self::RichText => 'rich_text',
            self::Button => 'button',
            self::Link => 'link',
            self::Image => 'image',
            self::Icon => 'choice',
            self::Items => 'items',
        };
    }
}
