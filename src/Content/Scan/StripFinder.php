<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Dom\Element;
use Dom\HTMLDocument;

/**
 * Finds the strips of a rendered page: the blocks of its content between the header and the footer of the site.
 *
 * The content starts at main; without main the finder goes down from the body for as long as only one block
 * holds content. Every block there is a strip, apart from the header, the footer and what holds no content. A
 * block that only wraps sections gives its sections, and a heading of its own is the title of the page.
 */
final class StripFinder
{
    /**
     * Parts of the site around the content; the navigation is passed by everywhere (Html::isIgnored()).
     */
    private const array LANDMARKS = ['header', 'footer', 'aside'];

    private const array LANDMARK_ROLES = ['banner', 'contentinfo', 'complementary'];

    /**
     * Blocks that are a strip of their own, also when they are the only one.
     */
    private const array SECTIONS = ['section', 'article'];

    /**
     * Elements of running text: content that only holds these is one strip.
     */
    private const array TEXT = [
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'dl', 'blockquote', 'figure', 'img', 'picture',
        'table', 'pre', 'hr', 'address', 'span', 'a', 'strong', 'em', 'small', 'b', 'i',
    ];

    /**
     * @return array{title: string, strips: list<Element>}
     */
    public function find(HTMLDocument $document): array
    {
        $body = $document->body;

        if (! $body instanceof Element) {
            return ['title' => self::documentTitle($document), 'strips' => []];
        }

        $root = $document->querySelector('main, [role="main"]') ?? $body;
        $blocks = $this->blocks($root);

        while (count($blocks) === 1 && ! in_array($blocks[0]->localName, self::SECTIONS, true)) {
            $inner = $this->blocks($blocks[0]);

            if ($inner === []) {
                break;
            }

            $root = $blocks[0];
            $blocks = $inner;
        }

        $headings = array_values(array_filter($blocks, static fn (Element $block): bool => $block->localName === 'h1'));
        $blocks = array_values(array_filter($blocks, static fn (Element $block): bool => $block->localName !== 'h1'));
        $title = $headings !== [] ? Html::text($headings[0]) : '';

        return [
            'title' => $title !== '' ? $title : self::documentTitle($document),
            'strips' => $this->strips($root, $blocks),
        ];
    }

    /**
     * @param  list<Element>  $blocks
     * @return list<Element>
     */
    private function strips(Element $root, array $blocks): array
    {
        if ($blocks === []) {
            return [];
        }

        $text = array_filter($blocks, static fn (Element $block): bool => in_array($block->localName, self::TEXT, true));

        if (count($text) === count($blocks)) {
            return [$root];
        }

        $strips = [];

        foreach ($blocks as $block) {
            $inner = in_array($block->localName, self::SECTIONS, true) ? [] : $this->blocks($block);
            $sections = array_filter($inner, static fn (Element $element): bool => in_array($element->localName, self::SECTIONS, true));

            if (count($inner) > 1 && count($sections) === count($inner)) {
                array_push($strips, ...$inner);
            } else {
                $strips[] = $block;
            }
        }

        return $strips;
    }

    /**
     * The child elements that hold content, without the header and the footer of the site.
     *
     * @return list<Element>
     */
    private function blocks(Element $element): array
    {
        return array_values(array_filter(Html::children($element), static fn (Element $child): bool => ! in_array($child->localName, self::LANDMARKS, true)
            && ! in_array($child->getAttribute('role'), self::LANDMARK_ROLES, true)
            && ! Html::isDecoration($child)
            && Html::hasContent($child)));
    }

    private static function documentTitle(HTMLDocument $document): string
    {
        $title = $document->querySelector('title');

        return $title instanceof Element ? Html::text($title) : '';
    }
}
