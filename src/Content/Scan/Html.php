<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Scan;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Text;

/**
 * Reads the rendered HTML of a page the way the scan needs it: what to pass by, what an element holds and its text.
 *
 * The scan only has the markup, never the computed styles: a background image counts when it is in the style
 * attribute, an element counts as hidden when the hidden attribute, aria-hidden or an inline display:none says so.
 */
final class Html
{
    /**
     * Elements the scan passes by with everything in them.
     */
    private const array IGNORED = [
        'script', 'style', 'template', 'noscript', 'link', 'meta', 'base', 'iframe', 'video', 'audio',
        'canvas', 'object', 'embed', 'map', 'dialog', 'nav',
    ];

    /**
     * Elements that place text in a line; any other element starts a block of its own.
     */
    private const array INLINE = [
        'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'cite', 'code', 'data', 'dfn', 'em', 'i', 'kbd', 'mark', 'q',
        's', 'samp', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'var', 'wbr', 'img', 'picture', 'svg',
    ];

    /**
     * Elements of a form: what a visitor fills in is no content of the site.
     */
    private const array FORM = ['form', 'input', 'select', 'textarea', 'option', 'fieldset', 'label', 'output', 'datalist'];

    /**
     * Stands for a br while the text is collected; the line breaks of the source are only white space.
     */
    private const string BREAK = "\u{1E}";

    public static function parse(string $html): HTMLDocument
    {
        if (trim($html) === '') {
            $html = '<!doctype html><html><head></head><body></body></html>';
        }

        return HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
    }

    /**
     * Passed by with everything in it: scripts, embeds, navigation, the elements of the CMS and what is hidden.
     */
    public static function isIgnored(Element $element): bool
    {
        $name = $element->localName;

        if (in_array($name, self::IGNORED, true) || str_starts_with($name, 'made-cms')) {
            return true;
        }

        if ($element->hasAttribute('hidden') || $element->getAttribute('role') === 'navigation') {
            return true;
        }

        return preg_match('/(^|;)\s*display\s*:\s*none/i', (string) $element->getAttribute('style')) === 1;
    }

    /**
     * Hidden from screen readers: decoration, whose texts the scan never reads.
     */
    public static function isDecoration(Element $element): bool
    {
        return $element->getAttribute('aria-hidden') === 'true';
    }

    public static function isForm(Element $element): bool
    {
        return in_array($element->localName, self::FORM, true);
    }

    /**
     * An svg, or an i without text as icon fonts use it.
     */
    public static function isIcon(Element $element): bool
    {
        return $element->localName === 'svg' || ($element->localName === 'i' && self::text($element) === '');
    }

    public static function isInline(Element $element): bool
    {
        return in_array($element->localName, self::INLINE, true);
    }

    public static function isHeading(Element $element): bool
    {
        return preg_match('/^h[1-6]$/', $element->localName) === 1;
    }

    /**
     * The child elements the scan looks at, in order.
     *
     * @return list<Element>
     */
    public static function children(Element $element): array
    {
        $children = [];

        // Dom\Element::$children only exists from PHP 8.5.
        for ($child = $element->firstElementChild; $child !== null; $child = $child->nextElementSibling) {
            if (! self::isIgnored($child)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * The element with its own tag, as markup (Dom\Element::$outerHTML only exists from PHP 8.5).
     */
    public static function outer(Element $element): string
    {
        $document = $element->ownerDocument;

        return $document instanceof HTMLDocument ? $document->saveHtml($element) : $element->innerHTML;
    }

    /**
     * Holds something a visitor reads or sees: text, an image or an inline background image.
     */
    public static function hasContent(Element $element): bool
    {
        if (self::isIgnored($element) || self::isForm($element) || self::isIcon($element)) {
            return false;
        }

        if (self::image($element) !== null || self::background($element) !== null) {
            return true;
        }

        if (self::isDecoration($element)) {
            return $element->querySelector('img') !== null;
        }

        if (self::ownText($element) !== '') {
            return true;
        }

        foreach (self::children($element) as $child) {
            if (self::hasContent($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The image of an img or a picture.
     *
     * @return array{url: string, alt: string}|null
     */
    public static function image(Element $element): ?array
    {
        $image = match ($element->localName) {
            'img' => $element,
            'picture' => $element->querySelector('img'),
            default => null,
        };

        if (! $image instanceof Element || trim((string) $image->getAttribute('src')) === '') {
            return null;
        }

        return ['url' => trim((string) $image->getAttribute('src')), 'alt' => trim((string) $image->getAttribute('alt'))];
    }

    /**
     * The url of a background image in the style attribute; a gradient is no image.
     */
    public static function background(Element $element): ?string
    {
        $style = (string) $element->getAttribute('style');

        if (preg_match('/background(?:-image)?\s*:[^;]*url\(\s*([\'"]?)(.+?)\1\s*\)/i', $style, $match) !== 1) {
            return null;
        }

        return trim($match[2]);
    }

    /**
     * The text a visitor reads in the element, on one line: without decoration, icons, scripts and forms.
     */
    public static function text(Element $element): string
    {
        return self::line(self::collect($element, ' '));
    }

    /**
     * The text of the element with a line for every br.
     */
    public static function lines(Element $element): string
    {
        $lines = array_map(self::line(...), explode(self::BREAK, self::collect($element, self::BREAK)));

        return trim(implode("\n", $lines));
    }

    /**
     * The text of the element itself, without the text of its child elements.
     */
    public static function ownText(Element $element): string
    {
        $text = '';

        foreach ($element->childNodes as $node) {
            if ($node instanceof Text) {
                $text .= $node->data;
            }
        }

        return self::line($text);
    }

    private static function collect(Element $element, string $break): string
    {
        if (self::isIgnored($element) || self::isDecoration($element) || self::isForm($element) || $element->localName === 'svg') {
            return '';
        }

        if ($element->localName === 'br') {
            return $break;
        }

        $text = '';
        $afterElement = false;

        foreach ($element->childNodes as $node) {
            if ($node instanceof Text) {
                $text .= $node->data;
                $afterElement = false;
            } elseif ($node instanceof Element) {
                $inner = self::collect($node, $break);
                // Elements right after each other are separate words, as a site in flex or grid shows them.
                $text .= self::isInline($node) && ! $afterElement ? $inner : ' '.$inner.' ';
                $afterElement = $inner !== '';
            }
        }

        return $text;
    }

    private static function line(string $text): string
    {
        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }
}
