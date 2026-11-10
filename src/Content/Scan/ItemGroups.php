<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Dom\Element;

/**
 * Finds the lists of a strip: two or more siblings in a row with the same tag and (nearly) the same structure.
 *
 * The structure of an element is the set of paths of tags to what it holds: its texts, images and icons, without
 * decoration and empty elements, and with every heading as "h", whatever its level. Siblings form a list when their sets overlap for at least SIMILAR (Jaccard), so
 * an item with an extra badge or without its image still belongs; empty siblings between them are passed by.
 * Running text never forms a list: paragraphs, headings and lists of lines stay fields of their own.
 */
final class ItemGroups
{
    public const float SIMILAR = 0.6;

    private const array NEVER = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'blockquote', 'br', 'hr'];

    /**
     * The lists among the children of an element, in order.
     *
     * @return list<array{members: list<Element>, exact: bool}>
     */
    public static function runs(Element $parent): array
    {
        $children = self::candidates($parent);
        $runs = [];
        $count = count($children);
        $index = 0;

        while ($index < $count) {
            $first = $children[$index];
            $paths = self::paths($first);
            $members = [$first];
            $exact = true;
            $next = $index + 1;

            while ($next < $count && ! in_array($first->localName, self::NEVER, true) && $children[$next]->localName === $first->localName) {
                $other = self::paths($children[$next]);

                if (self::similarity($paths, $other) < self::SIMILAR) {
                    break;
                }

                $exact = $exact && $other === $paths;
                $members[] = $children[$next];
                $next++;
            }

            if (count($members) > 1) {
                $runs[] = ['members' => $members, 'exact' => $exact];
            }

            $index = $next;
        }

        return $runs;
    }

    /**
     * The paths of tags to the texts, images and icons in the element, each once and sorted.
     *
     * @return list<string>
     */
    public static function paths(Element $element, string $prefix = ''): array
    {
        $paths = [];

        foreach (Html::children($element) as $child) {
            if (Html::isIcon($child) && ! Html::isDecoration($child)) {
                $paths[] = $prefix.'icon';

                continue;
            }

            if (! Html::hasContent($child)) {
                continue;
            }

            // The level of a heading is content: items with an h3 and an h4 in the same place are built alike.
            $path = $prefix.(Html::isHeading($child) ? 'h' : $child->localName);
            $paths[] = $path;

            if (! Html::isDecoration($child)) {
                array_push($paths, ...self::paths($child, $path.'/'));
            }
        }

        $paths = array_values(array_unique($paths));
        sort($paths);

        return $paths;
    }

    /**
     * @param  list<string>  $first
     * @param  list<string>  $second
     */
    public static function similarity(array $first, array $second): float
    {
        $all = array_unique([...$first, ...$second]);

        return $all === [] ? 1.0 : count(array_intersect($first, $second)) / count($all);
    }

    /**
     * The children that can be an item: elements with content that are no decoration.
     *
     * @return list<Element>
     */
    private static function candidates(Element $parent): array
    {
        return array_values(array_filter(
            Html::children($parent),
            static fn (Element $child): bool => ! Html::isDecoration($child) && ! Html::isForm($child) && Html::hasContent($child),
        ));
    }
}
