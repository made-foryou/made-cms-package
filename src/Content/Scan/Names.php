<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Dom\Element;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Support\Locale;

/**
 * The names the scan gives: generic, with the id or a class of a strip as a hint for its type.
 *
 * A type is named after the id or class of its strips, else after its place ("strook-3"); a field after its
 * role ("kop", "tekst", "knop"), numbered when the same name comes back. The words are those of the launcher's
 * language, so the generated keys read like the site's own. Better names are for the AI step of the scan.
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

    /**
     * The keys of the types, in order: from their hints, generic without one, numbered when taken.
     *
     * @param  list<string|null>  $hints
     * @return list<string>
     */
    public static function typeKeys(array $hints): array
    {
        $generic = self::word('made-cms-package::scan.strip', 'strip', '-');
        $keys = [];

        foreach ($hints as $index => $hint) {
            $base = $hint === null ? '' : Str::slug(substr($hint, 1));

            if (preg_match('/^[a-z][a-z0-9-]*$/', $base) !== 1) {
                $base = $generic.'-'.($index + 1);
            }

            $key = $base;
            $number = 2;

            while (in_array($key, $keys, true)) {
                $key = $base.'-'.$number++;
            }

            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * Gives every field (and every field of a list) a key after its role, unique among its siblings.
     *
     * @param  list<Proposal>  $fields
     */
    public static function fieldKeys(array $fields): void
    {
        $taken = StripTypes::RESERVED_FIELDS;

        foreach ($fields as $field) {
            $role = $field->name ?? $field->role;
            $base = self::word('made-cms-package::scan.keys.'.$role->value, $role->value, '_');
            $key = $base;
            $number = 2;

            while (in_array($key, $taken, true)) {
                $key = $base.'_'.$number++;
            }

            $field->key = $key;
            $taken[] = $key;

            self::fieldKeys($field->fields);
        }
    }

    /**
     * The label of a key, as Field::getLabel() makes one.
     */
    public static function label(string $key): string
    {
        return Str::ucfirst(str_replace(['-', '_'], ' ', $key));
    }

    /**
     * A translated word as a key: lowercase letters, digits and the separator; the fallback when nothing is left.
     */
    private static function word(string $translation, string $fallback, string $separator): string
    {
        $word = Str::slug((string) __($translation, [], Locale::launcher()), $separator);

        return preg_match('/^[a-z][a-z0-9'.preg_quote($separator, '/').']*$/', $word) === 1 ? $word : $fallback;
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
