<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Made\CmsPackage\Content\Data\RecognisedField;
use Made\CmsPackage\Content\Data\RecognisedPage;
use Made\CmsPackage\Content\Data\RecognisedStrip;
use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScanPage;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Data\ScanStrip;
use Made\CmsPackage\Content\Data\ScanType;
use Made\CmsPackage\Content\Enums\ScanDoubt;
use Made\CmsPackage\Content\Enums\ScanRole;
use Made\CmsPackage\Content\Strips\Emphasis;
use Made\CmsPackage\Content\Strips\Fields\Heading;

/**
 * Combines what the scan read on every page into strip types, and decides what needs all of them.
 *
 * Strips with the same fingerprint are one type. Their fields line up by selector, and a field that one
 * instance lacks is optional. Over all items of all instances of a list it decides:
 * - icons that differ per item are a choice, icons that are all the same decoration;
 * - a link with the same text in every item is a link of its own (the text belongs to the design), with the
 *   same address too it is left out, like an image or link that is the same everywhere;
 * - a text that is the same in every item is left out when it is a short label (two words at most, no digits),
 *   and doubted otherwise; for a heading only its text counts.
 *
 * A heading proposes the level most of its examples have, the highest heading when levels are as frequent, and
 * is doubted when its examples have more than one level; every strip keeps the level it had.
 */
final class TypeMerger
{
    /**
     * @param  list<RecognisedPage>  $pages
     */
    public function merge(array $pages): ScanResult
    {
        $pages = self::unique($pages);
        $groups = [];

        foreach ($pages as $page) {
            foreach ($page->strips as $strip) {
                $groups[$strip->fingerprint] ??= ['hint' => null, 'strips' => []];
                $groups[$strip->fingerprint]['hint'] ??= $strip->hint;
                $groups[$strip->fingerprint]['strips'][] = $strip;
            }
        }

        $proposed = [];

        foreach ($groups as $fingerprint => $group) {
            $fields = $this->resolve(array_map(static fn (RecognisedStrip $strip): array => $strip->fields, $group['strips']), false);

            if ($fields !== []) {
                $proposed[] = ['fingerprint' => (string) $fingerprint, 'hint' => $group['hint'], 'strips' => $group['strips'], 'fields' => $fields];
            }
        }

        $keys = Names::typeKeys(array_column($proposed, 'hint'));
        $types = [];
        $placed = [];

        foreach ($proposed as $index => $type) {
            Names::fieldKeys($type['fields']);
            $doubts = $type['hint'] === null ? [ScanDoubt::GenericName] : [];

            foreach ($type['strips'] as $instance => $strip) {
                $doubts = [...$doubts, ...$strip->doubts];
                $placed[spl_object_id($strip)] = [$keys[$index], Proposal::valuesOf($type['fields'], $instance)];
            }

            $types[] = new ScanType(
                key: $keys[$index],
                label: Names::label($keys[$index]),
                hint: $type['hint'],
                fingerprint: $type['fingerprint'],
                count: count($type['strips']),
                fields: array_map(static fn (Proposal $field): ScanField => $field->toField(), $type['fields']),
                doubts: ScanDoubt::sorted($doubts),
            );
        }

        $scanned = [];

        foreach ($pages as $page) {
            $strips = [];

            foreach ($page->strips as $strip) {
                $place = $placed[spl_object_id($strip)] ?? null;

                if ($place !== null) {
                    $strips[] = new ScanStrip($place[0], $strip->selector, $place[1]);
                }
            }

            $scanned[] = new ScanPage($page->url, $page->title, $strips);
        }

        return new ScanResult($types, $scanned);
    }

    /**
     * The fields of a type, or of the items of a list, from the fields of every instance.
     *
     * @param  list<list<RecognisedField>>  $instances
     * @return list<Proposal>
     */
    private function resolve(array $instances, bool $inItems): array
    {
        $proposals = [];

        foreach (self::align($instances) as $column) {
            $proposal = $this->column($column['selector'], $column['cells'], count($instances), $inItems);

            if ($proposal instanceof Proposal) {
                $proposals[] = $proposal;
            }
        }

        return $proposals;
    }

    /**
     * @param  array<int, RecognisedField>  $cells  by instance
     */
    private function column(string $selector, array $cells, int $instances, bool $inItems): ?Proposal
    {
        $first = $cells[array_key_first($cells)] ?? null;

        if (! $first instanceof RecognisedField) {
            return null;
        }

        $role = self::widest($cells);
        $everywhere = count($cells) === $instances;
        $doubts = ScanDoubt::sorted(array_merge(...array_values(array_map(static fn (RecognisedField $cell): array => $cell->doubts, $cells))));

        if ($role === ScanRole::Items) {
            return $this->items($selector, $cells, $everywhere, $doubts);
        }

        if ($role === ScanRole::Icon) {
            return $inItems ? self::choice($selector, $cells, $everywhere) : null;
        }

        // A heading has emphasis when one of its examples had an <em>; in a column that is no heading it is plain text.
        $emphasis = $role === ScanRole::Heading && array_filter($cells, static fn (RecognisedField $cell): bool => $cell->emphasis) !== [];
        $values = array_map(static fn (RecognisedField $cell): mixed => self::convert($cell->emphasis && ! $emphasis ? self::withoutEmphasis($cell->value) : $cell->value, $cell->role, $role), $cells);
        $compared = $role === ScanRole::Heading ? array_map(self::textOf(...), $values) : $values;
        $same = $inItems && $everywhere && $instances > 1;
        $levels = $role === ScanRole::Heading ? self::levels($values) : [];

        if (count($levels) > 1) {
            $doubts = ScanDoubt::sorted([...$doubts, ScanDoubt::HeadingLevelsDiffer]);
        }

        if ($same && $role === ScanRole::Button && count(array_unique(array_map(self::textOf(...), $values))) === 1) {
            $links = array_map(static fn (mixed $value): mixed => is_array($value) ? $value['link'] ?? null : null, $values);

            if (count(array_unique(array_map(self::encode(...), $links))) === 1) {
                return null;
            }

            [$role, $values] = [ScanRole::Link, $links];
        } elseif ($same && count(array_unique(array_map(self::encode(...), $compared))) === 1) {
            if (! in_array($role, [ScanRole::Heading, ScanRole::Text, ScanRole::Paragraph, ScanRole::List, ScanRole::RichText], true) || self::isLabel(reset($compared))) {
                return null;
            }

            $doubts = ScanDoubt::sorted([...$doubts, ScanDoubt::SameInEveryItem]);
        }

        $filled = array_filter($values, self::isFilled(...));

        return new Proposal(
            role: $role,
            selector: $selector,
            required: $everywhere && count($filled) === count($values),
            doubts: $doubts,
            values: $filled,
            name: $role === ScanRole::Link || $first->role === ScanRole::Link ? null : $first->role,
            tag: $levels[0] ?? null,
            emphasis: $emphasis,
        );
    }

    /**
     * A heading without the asterisks of its emphasis.
     */
    private static function withoutEmphasis(mixed $value): mixed
    {
        return is_array($value) && is_string($value['text'] ?? null) ? [...$value, 'text' => Emphasis::plain($value['text'])] : $value;
    }

    /**
     * The levels of the examples of a heading, the most frequent first and the highest heading first when levels
     * are as frequent.
     *
     * @param  array<int, mixed>  $values
     * @return list<string>
     */
    private static function levels(array $values): array
    {
        $counts = [];

        foreach ($values as $value) {
            $tag = is_array($value) && is_string($value['tag'] ?? null) ? $value['tag'] : null;

            if ($tag !== null) {
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        $order = array_flip(Heading::TAGS);
        $levels = array_keys($counts);

        usort($levels, static fn (string $a, string $b): int => [$counts[$b], $order[$a] ?? PHP_INT_MAX] <=> [$counts[$a], $order[$b] ?? PHP_INT_MAX]);

        return $levels;
    }

    /**
     * A list: the fields of its items over all instances, with the items of every instance.
     *
     * @param  array<int, RecognisedField>  $cells
     * @param  list<ScanDoubt>  $doubts
     */
    private function items(string $selector, array $cells, bool $everywhere, array $doubts): ?Proposal
    {
        $items = [];
        $indexes = [];
        $max = 0;
        $tag = null;

        foreach ($cells as $instance => $cell) {
            $indexes[$instance] = [];
            $tag ??= $cell->item;
            $max = max($max, count($cell->items));

            foreach ($cell->items as $fields) {
                $indexes[$instance][] = count($items);
                $items[] = $fields;
            }
        }

        $fields = $this->resolve($items, true);

        return $fields === [] ? null : new Proposal(
            role: ScanRole::Items,
            selector: $selector,
            required: $everywhere,
            doubts: $doubts,
            values: $indexes,
            item: $tag,
            max: $max,
            fields: $fields,
        );
    }

    /**
     * Icons that differ per item: each item chooses the class that sets it apart, without the prefix they share.
     *
     * @param  array<int, RecognisedField>  $cells
     */
    private static function choice(string $selector, array $cells, bool $everywhere): ?Proposal
    {
        $tokens = array_map(static fn (RecognisedField $cell): array => array_values(array_filter(is_array($cell->value) ? $cell->value : [], is_string(...))), $cells);

        if (count($tokens) < 2 || count(array_unique(array_map(self::encode(...), $tokens))) === 1) {
            return null;
        }

        $shared = array_values(array_intersect(...array_values($tokens)));
        $values = array_filter(array_map(static fn (array $classes): string => array_values(array_diff($classes, $shared))[0] ?? '', $tokens), static fn (string $value): bool => $value !== '');
        $options = array_values(array_unique($values));
        $prefix = self::prefix($options);
        $values = array_map(static fn (string $value): string => substr($value, strlen($prefix)), $values);

        return new Proposal(
            role: ScanRole::Icon,
            selector: $selector,
            required: $everywhere && count($values) === count($cells),
            doubts: [ScanDoubt::IconChoice],
            values: $values,
            options: array_values(array_unique($values)),
        );
    }

    /**
     * Lines up the fields of the instances by their place: a field one instance lacks goes after the field that
     * comes before it in the instance that has it.
     *
     * A heading lines up by its parent and its place among the headings the scan read there, not by its selector:
     * its level is content, so the h2 of one page and the h3 of another are one field. An empty heading the scan
     * passed by does not count, so an empty heading before a filled one on one page only can shift the line-up.
     *
     * @param  list<list<RecognisedField>>  $instances
     * @return list<array{selector: string, cells: array<int, RecognisedField>}>
     */
    private static function align(array $instances): array
    {
        $order = [];
        $columns = [];

        foreach ($instances as $instance => $fields) {
            $previous = null;
            $headings = [];

            foreach ($fields as $field) {
                $key = self::place($field, $headings);

                if (! isset($columns[$key])) {
                    $columns[$key] = ['selector' => $field->selector, 'cells' => []];
                    $position = $previous === null ? 0 : (int) array_search($previous, $order, true) + 1;
                    array_splice($order, $position, 0, [$key]);
                }

                $columns[$key]['cells'][$instance] ??= $field;
                $previous = $key;
            }
        }

        return array_map(static fn (string $key): array => $columns[$key], $order);
    }

    /**
     * Where a field stands in its instance: its selector and the family of its role, or for a heading the selector
     * of its parent and its number among the headings in that parent.
     *
     * @param  array<string, int>  $headings  the headings counted so far per parent
     */
    private static function place(RecognisedField $field, array &$headings): string
    {
        if ($field->role !== ScanRole::Heading) {
            return $field->selector.'|'.self::family($field->role);
        }

        $steps = explode(' > ', $field->selector);
        array_pop($steps);
        $parent = implode(' > ', $steps);
        $headings[$parent] = ($headings[$parent] ?? 0) + 1;

        return $parent.'|heading|'.$headings[$parent];
    }

    /**
     * Roles that can stand in the same place on different pages.
     */
    private static function family(ScanRole $role): string
    {
        return match ($role) {
            ScanRole::Text, ScanRole::Paragraph, ScanRole::List, ScanRole::RichText => 'text',
            ScanRole::Button, ScanRole::Link => 'link',
            default => $role->value,
        };
    }

    /**
     * The role that holds the values of every cell: a text widens to a long text and to rich text, a link to a
     * button.
     *
     * @param  array<int, RecognisedField>  $cells
     */
    private static function widest(array $cells): ScanRole
    {
        $rank = static fn (ScanRole $role): int => match ($role) {
            ScanRole::Paragraph, ScanRole::List, ScanRole::Button => 1,
            ScanRole::RichText => 2,
            default => 0,
        };

        $widest = reset($cells)->role ?? ScanRole::Text;

        foreach ($cells as $cell) {
            if ($rank($cell->role) > $rank($widest)) {
                $widest = $cell->role;
            }
        }

        return $widest;
    }

    private static function convert(mixed $value, ScanRole $from, ScanRole $to): mixed
    {
        if ($to === ScanRole::RichText && $from !== ScanRole::RichText && is_string($value)) {
            $lines = array_filter(explode("\n", $value), static fn (string $line): bool => trim($line) !== '');

            return implode('', array_map(static fn (string $line): string => '<p>'.htmlspecialchars(trim($line), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</p>', $lines));
        }

        if ($to === ScanRole::Button && $from === ScanRole::Link) {
            return ['text' => '', 'link' => $value];
        }

        return $value;
    }

    private static function isFilled(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (! is_array($value)) {
            return $value !== null;
        }

        if (array_key_exists('tag', $value)) {
            return trim(self::textOf($value)) !== '';
        }

        if (array_key_exists('text', $value)) {
            return self::textOf($value) !== '' && self::isFilled($value['link'] ?? null);
        }

        if (array_key_exists('url', $value)) {
            return is_string($value['url']) && trim($value['url']) !== '';
        }

        return $value !== [];
    }

    /**
     * A short label of the design rather than content: two words at most and no digits.
     */
    private static function isLabel(mixed $value): bool
    {
        $text = is_string($value) ? trim(strip_tags($value)) : '';
        $words = preg_split('/\s+/u', $text) ?: [];

        return $text !== '' && count($words) <= 2 && preg_match('/\d/', $text) !== 1;
    }

    /**
     * The text of a button or a heading.
     */
    private static function textOf(mixed $value): string
    {
        return is_array($value) && is_string($value['text'] ?? null) ? $value['text'] : '';
    }

    private static function encode(mixed $value): string
    {
        return (string) json_encode($value);
    }

    /**
     * The prefix up to a dash that every option shares, when every option keeps a name after it.
     *
     * @param  list<string>  $options
     */
    private static function prefix(array $options): string
    {
        $prefix = (string) array_reduce($options, static function (?string $carry, string $option): string {
            if ($carry === null) {
                return $option;
            }

            $length = 0;

            while ($length < min(strlen($carry), strlen($option)) && $carry[$length] === $option[$length]) {
                $length++;
            }

            return substr($carry, 0, $length);
        });

        $dash = strrpos($prefix, '-');

        return $dash === false ? '' : substr($prefix, 0, $dash + 1);
    }

    /**
     * Every address once: the scan may send a page twice.
     *
     * @param  list<RecognisedPage>  $pages
     * @return list<RecognisedPage>
     */
    private static function unique(array $pages): array
    {
        $unique = [];

        foreach ($pages as $page) {
            $unique[$page->url] ??= $page;
        }

        return array_values($unique);
    }
}
