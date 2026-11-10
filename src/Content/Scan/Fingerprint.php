<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Dom\Element;

/**
 * The structure of a strip as a hash: the tree of its tags without texts, attributes, icons and decoration.
 *
 * Strips with the same fingerprint are instances of one strip type, on one page or on several. Repeats count once:
 * the items of a list, a list inside an item and empty decoration in a row, so the number of items never matters.
 * The level of a heading is content, not structure: every heading is "h", and each counts, also in a row.
 */
final class Fingerprint
{
    /**
     * The shape of every heading, h1 to h6.
     */
    private const string HEADING = 'h()';

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

        // Markup inside running text, such as a link or bold, is content rather than structure.
        if (Html::isHeading($element)) {
            return self::HEADING;
        }

        if ($element->localName === 'p') {
            return 'p()';
        }

        $runs = [];

        foreach (ItemGroups::runs($element) as $run) {
            $shapes = array_values(array_unique(array_map(self::shape(...), $run['members'])));
            sort($shapes);
            $shape = count($shapes) === 1 ? $shapes[0] : $run['members'][0]->localName.'['.implode('|', $shapes).']';

            foreach ($run['members'] as $member) {
                $runs[spl_object_id($member)] = $shape;
            }
        }

        $children = [];

        foreach (Html::children($element) as $child) {
            $shape = $runs[spl_object_id($child)] ?? self::child($child);

            if ($shape !== null && (end($children) !== $shape || $shape === self::HEADING)) {
                $children[] = $shape;
            }
        }

        return $element->localName.'('.implode(',', $children).')';
    }

    /**
     * The shape of a child; nothing for an icon or decoration, a hidden image only counts as an image.
     */
    private static function child(Element $child): ?string
    {
        if (Html::isIcon($child)) {
            return null;
        }

        if (Html::isDecoration($child)) {
            return Html::image($child) !== null ? 'img' : null;
        }

        return self::shape($child);
    }
}
