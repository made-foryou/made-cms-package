<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Dom\Element;
use Made\CmsPackage\Content\Data\RecognisedField;
use Made\CmsPackage\Content\Enums\ScanDoubt;
use Made\CmsPackage\Content\Enums\ScanRole;
use Made\CmsPackage\Content\Strips\Fields\RichText;

/**
 * Reads the fields of a strip from its markup, in the order of the document.
 *
 * A heading is a heading with its level ({text, tag}), a paragraph a long text, a block of paragraphs or a list of plain lines rich text, a link a
 * button or (around more than text, or without text) a link, an img, a picture or an inline background an image.
 * Forms, buttons of the interface, hidden texts and icons are passed by; inside an item of a list an icon stays,
 * because the icons of the items can be a choice.
 */
final class FieldReader
{
    /**
     * Blocks of running text that, two or more together, make rich text.
     */
    private const array PROSE = ['p', 'ul', 'ol', 'blockquote'];

    /**
     * @return array{fields: list<RecognisedField>, doubts: list<ScanDoubt>}
     */
    public function read(Element $strip): array
    {
        return [
            'fields' => $this->element($strip, $strip, false),
            'doubts' => $strip->querySelector('form') !== null ? [ScanDoubt::ContainsForm] : [],
        ];
    }

    /**
     * @return list<RecognisedField>
     */
    private function element(Element $element, Element $root, bool $inItem): array
    {
        if (Html::isIgnored($element)) {
            return [];
        }

        if (Html::isForm($element)) {
            return [];
        }

        if (Html::isDecoration($element)) {
            return $this->hiddenImages($element, $root);
        }

        $selector = Selector::relative($element, $root);

        if (Html::isIcon($element)) {
            return $inItem ? [new RecognisedField(ScanRole::Icon, $selector, self::classes($element))] : [];
        }

        $image = Html::image($element);

        if ($image !== null) {
            return [new RecognisedField(ScanRole::Image, $selector, $image, $image['alt'] === '' ? [ScanDoubt::DecorativeImage] : [])];
        }

        $background = Html::background($element);
        $fields = $background === null ? [] : [
            new RecognisedField(ScanRole::Image, $selector, ['url' => $background, 'alt' => ''], [ScanDoubt::BackgroundImage]),
        ];

        return [...$fields, ...$this->content($element, $root, $selector, $inItem)];
    }

    /**
     * @return list<RecognisedField>
     */
    private function content(Element $element, Element $root, string $selector, bool $inItem): array
    {
        $name = $element->localName;

        if ($name === 'a' && $element->hasAttribute('href')) {
            return $this->anchor($element, $root, $selector, $inItem);
        }

        if ($name === 'button') {
            return [];
        }

        if (Html::isHeading($element)) {
            $text = Html::text($element);

            return $text === '' ? [] : [new RecognisedField(ScanRole::Heading, $selector, ['text' => $text, 'tag' => $name])];
        }

        if ($name === 'p' && ! $this->holdsOnlyLinks($element)) {
            return $this->paragraph($element, $selector);
        }

        if ($this->isProse($element)) {
            $html = in_array($name, ['ul', 'ol'], true) ? Html::outer($element) : $element->innerHTML;

            return [new RecognisedField(ScanRole::RichText, $selector, self::richText($html))];
        }

        if (Html::ownText($element) !== '' && ! $this->holdsBlocks($element)) {
            return [new RecognisedField(ScanRole::Text, $selector, Html::text($element))];
        }

        return $this->container($element, $root, $selector, $inItem);
    }

    /**
     * @return list<RecognisedField>
     */
    private function container(Element $element, Element $root, string $selector, bool $inItem): array
    {
        $fields = Html::ownText($element) === '' ? [] : [new RecognisedField(ScanRole::Text, $selector, Html::ownText($element))];
        $starts = [];
        $members = [];

        foreach (ItemGroups::runs($element) as $run) {
            $starts[spl_object_id($run['members'][0])] = $run;

            foreach ($run['members'] as $member) {
                $members[spl_object_id($member)] = true;
            }
        }

        foreach (Html::children($element) as $child) {
            $run = $starts[spl_object_id($child)] ?? null;

            if ($run !== null) {
                $fields[] = $inItem ? $this->nestedList($run['members'], $selector) : $this->items($run, $selector);
            } elseif (! isset($members[spl_object_id($child)])) {
                array_push($fields, ...$this->element($child, $root, $inItem));
            }
        }

        return $fields;
    }

    /**
     * A list: the fields of every item, each with selectors from the root of its item.
     *
     * @param  array{members: list<Element>, exact: bool}  $run
     */
    private function items(array $run, string $selector): RecognisedField
    {
        return new RecognisedField(
            role: ScanRole::Items,
            selector: $selector,
            value: null,
            doubts: $run['exact'] ? [] : [ScanDoubt::ItemsDiffer],
            item: $run['members'][0]->localName,
            items: array_map(fn (Element $member): array => $this->element($member, $member, true), $run['members']),
        );
    }

    /**
     * A repeat inside an item cannot be a list of its own: it becomes a long text with a line per element.
     *
     * @param  list<Element>  $members
     */
    private function nestedList(array $members, string $selector): RecognisedField
    {
        $lines = [];

        foreach ($members as $member) {
            $line = Html::ownText($member) !== '' ? Html::ownText($member) : Html::text($member);

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return new RecognisedField(ScanRole::List, $selector, implode("\n", $lines), [ScanDoubt::NestedList]);
    }

    /**
     * A link around a card or an image is a link with the fields in it; a link with a text of its own a button.
     *
     * @return list<RecognisedField>
     */
    private function anchor(Element $anchor, Element $root, string $selector, bool $inItem): array
    {
        $link = ['url' => trim((string) $anchor->getAttribute('href'))];

        if ($this->holdsBlocks($anchor) || $this->holdsImage($anchor)) {
            $fields = [new RecognisedField(ScanRole::Link, $selector, $link)];

            foreach (Html::children($anchor) as $child) {
                array_push($fields, ...$this->element($child, $root, $inItem));
            }

            return $fields;
        }

        $text = Html::text($anchor);

        return [$text === ''
            ? new RecognisedField(ScanRole::Link, $selector, $link)
            : new RecognisedField(ScanRole::Button, $selector, ['text' => $text, 'link' => $link])];
    }

    /**
     * @return list<RecognisedField>
     */
    private function paragraph(Element $paragraph, string $selector): array
    {
        if ($paragraph->querySelector('a[href]') !== null) {
            return [new RecognisedField(ScanRole::RichText, $selector, self::richText(Html::outer($paragraph)))];
        }

        $lines = Html::lines($paragraph);

        return $lines === '' ? [] : [new RecognisedField(ScanRole::Paragraph, $selector, $lines)];
    }

    /**
     * Images inside decoration: a photo behind a hero is often hidden from screen readers.
     *
     * @return list<RecognisedField>
     */
    private function hiddenImages(Element $element, Element $root): array
    {
        $images = in_array($element->localName, ['img', 'picture'], true) ? [$element] : $element->querySelectorAll('img');
        $fields = [];

        foreach ($images as $image) {
            $value = Html::image($image);

            if ($value !== null) {
                $fields[] = new RecognisedField(ScanRole::Image, Selector::relative($image, $root), $value, [ScanDoubt::DecorativeImage]);
            }
        }

        return $fields;
    }

    /**
     * Two or more blocks of running text and nothing else, or a list of plain lines.
     */
    private function isProse(Element $element): bool
    {
        if (in_array($element->localName, ['ul', 'ol'], true)) {
            return $this->isPlainList($element);
        }

        $blocks = $this->contentChildren($element);

        if (count($blocks) < 2 || Html::ownText($element) !== '') {
            return false;
        }

        foreach ($blocks as $block) {
            $prose = in_array($block->localName, self::PROSE, true)
                && (! in_array($block->localName, ['ul', 'ol'], true) || $this->isPlainList($block));

            if (! $prose || ($block->localName === 'p' && $this->holdsBlocks($block))) {
                return false;
            }
        }

        return true;
    }

    private function isPlainList(Element $list): bool
    {
        if (! in_array($list->localName, ['ul', 'ol'], true)) {
            return false;
        }

        $items = $this->contentChildren($list);

        foreach ($items as $item) {
            if ($item->localName !== 'li' || $this->holdsBlocks($item) || $this->holdsImage($item)) {
                return false;
            }
        }

        return $items !== [];
    }

    /**
     * A paragraph that only holds links, such as a row of buttons, is no text of its own.
     */
    private function holdsOnlyLinks(Element $paragraph): bool
    {
        $children = $this->contentChildren($paragraph);

        if (Html::ownText($paragraph) !== '' || $children === []) {
            return false;
        }

        foreach ($children as $child) {
            if ($child->localName !== 'a') {
                return false;
            }
        }

        return true;
    }

    /**
     * Holds a block with content: a heading, a paragraph or any other element that does not run in a line.
     */
    private function holdsBlocks(Element $element): bool
    {
        foreach ($this->contentChildren($element) as $child) {
            if (! Html::isInline($child) || $this->holdsBlocks($child)) {
                return true;
            }
        }

        return false;
    }

    private function holdsImage(Element $element): bool
    {
        return $element->querySelector('img') !== null;
    }

    /**
     * @return list<Element>
     */
    private function contentChildren(Element $element): array
    {
        return array_values(array_filter(
            Html::children($element),
            static fn (Element $child): bool => ! Html::isDecoration($child) && Html::hasContent($child),
        ));
    }

    /**
     * @return list<string>
     */
    private static function classes(Element $element): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($element->className)) ?: [], static fn (string $class): bool => $class !== ''));
    }

    private static function richText(string $html): string
    {
        $value = RichText::make('scan')->normalize(trim($html));

        return is_string($value) ? trim($value) : '';
    }
}
