<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Dom\Element;

/**
 * The names the scan gives: generic, with the id or a class of a strip as a hint for its type.
 */
final class Names
{
    /**
     * Classes that place or style an element instead of naming it, by their first word: Tailwind and Bootstrap
     * utilities, states and hooks for scripts.
     */
    private const array UTILITIES = [
        'absolute', 'accent', 'align', 'animate', 'antialiased', 'appearance', 'aspect', 'backdrop', 'basis', 'bg',
        'block', 'blur', 'border', 'bottom', 'box', 'break', 'btn', 'capitalize', 'caret', 'clear', 'col', 'columns',
        'container', 'content', 'contents', 'cursor', 'd', 'decoration', 'delay', 'display', 'divide', 'drop',
        'duration', 'ease', 'end', 'fill', 'filter', 'fixed', 'flex', 'float', 'flow', 'font', 'from', 'g', 'gap',
        'grid', 'group', 'grow', 'gx', 'gy', 'h', 'has', 'hidden', 'inline', 'inset', 'invisible', 'is', 'isolate',
        'italic', 'items', 'js', 'justify', 'leading', 'left', 'line', 'list', 'lowercase', 'm', 'max', 'mb', 'me',
        'min', 'mix', 'ml', 'mr', 'ms', 'mt', 'mx', 'my', 'no', 'object', 'opacity', 'order', 'outline', 'overflow',
        'overscroll', 'p', 'pb', 'pe', 'peer', 'pl', 'place', 'pointer', 'position', 'pr', 'ps', 'pt', 'px', 'py',
        'relative', 'resize', 'right', 'ring', 'rotate', 'rounded', 'row', 'scale', 'scroll', 'select', 'self',
        'shadow', 'shrink', 'skew', 'snap', 'space', 'sr', 'start', 'static', 'sticky', 'stroke', 'table', 'text',
        'to', 'top', 'touch', 'tracking', 'transform', 'transition', 'translate', 'truncate', 'underline',
        'uppercase', 'via', 'visible', 'visually', 'w', 'whitespace', 'will', 'z',
    ];

    /**
     * The id (#over-ons) or the first class that reads as a name (.hero) of a strip; null when it has neither.
     */
    public static function hint(Element $strip): ?string
    {
        if (preg_match('/^[A-Za-z][\w-]*$/', $strip->id) === 1) {
            return '#'.$strip->id;
        }

        foreach (preg_split('/\s+/', trim($strip->className)) ?: [] as $class) {
            if (self::readsAsName($class)) {
                return '.'.$class;
            }
        }

        return null;
    }

    private static function readsAsName(string $class): bool
    {
        if (preg_match('/^[a-z][a-z-]*(?:__[a-z-]+)?$/i', $class) !== 1) {
            return false;
        }

        $first = strtolower((string) preg_replace('/[-_].*$/', '', $class));

        return ! in_array($first, self::UTILITIES, true) && ! in_array(strtolower($class), self::UTILITIES, true);
    }
}
