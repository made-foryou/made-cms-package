<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Dom\Element;

/**
 * Where an element is, as a CSS selector that finds it in PHP and in the browser alike.
 *
 * A step is the tag of the element, with :nth-of-type(n) from the second element of that tag among its siblings
 * on; the steps are joined by " > ". The first match of such a path is always the element itself, and a sibling
 * of another tag that is missing on one page does not change it.
 */
final class Selector
{
    /**
     * The path from the root to the element, without the root; empty for the root itself. The element is found
     * again with document.querySelector(root + ' > ' + path).
     */
    public static function relative(Element $element, Element $root): string
    {
        $steps = [];
        $current = $element;

        while ($current instanceof Element && $current !== $root) {
            array_unshift($steps, self::step($current));
            $current = $current->parentElement;
        }

        return implode(' > ', $steps);
    }

    /**
     * Where the element is in its document: its id when no other element has it, else its path from the body.
     */
    public static function absolute(Element $element): string
    {
        $id = $element->id;

        if (preg_match('/^[A-Za-z][\w-]*$/', $id) === 1 && $element->ownerDocument?->querySelectorAll('#'.$id)->length === 1) {
            return '#'.$id;
        }

        $body = $element->ownerDocument?->body;

        return $body instanceof Element && $element !== $body
            ? 'body > '.self::relative($element, $body)
            : 'body';
    }

    private static function step(Element $element): string
    {
        $position = 1;
        $sibling = $element->previousElementSibling;

        while ($sibling instanceof Element) {
            if ($sibling->localName === $element->localName) {
                $position++;
            }

            $sibling = $sibling->previousElementSibling;
        }

        return $position === 1 ? $element->localName : $element->localName.':nth-of-type('.$position.')';
    }
}
