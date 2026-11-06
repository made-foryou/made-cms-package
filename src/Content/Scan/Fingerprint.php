<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Dom\Element;

/**
 * The structure of a strip as a hash: the tree of its tags without texts, attributes, icons and decoration.
 *
 * Strips with the same fingerprint are instances of one strip type, on one page or on several.
 */
final class Fingerprint
{
    public static function of(Element $element): string
    {
        return hash('xxh128', self::shape($element));
    }

    /**
     * The tree of tags the hash is made of, such as section(div(h2(),p())).
     */
    public static function shape(Element $element): string
    {
        if (Html::isForm($element)) {
            return 'form';
        }

        $children = [];

        foreach (Html::children($element) as $child) {
            if (Html::isIcon($child)) {
                continue;
            }

            if (Html::isDecoration($child)) {
                if (Html::image($child) !== null) {
                    $children[] = 'img';
                }

                continue;
            }

            $children[] = self::shape($child);
        }

        return $element->localName.'('.implode(',', $children).')';
    }
}
