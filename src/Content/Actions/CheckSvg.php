<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Dom\Element;
use Dom\Node;
use Dom\ProcessingInstruction;
use Dom\XMLDocument;
use DOMException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use ValueError;

final class CheckSvg
{
    use AsAction;

    private const string SVG = 'http://www.w3.org/2000/svg';

    private const string XHTML = 'http://www.w3.org/1999/xhtml';

    /**
     * The elements of SVG that only draw. Everything else of SVG is refused: script, foreignObject (which holds
     * html), and whatever a later version of SVG adds.
     */
    private const array ELEMENTS = [
        'a', 'animate', 'animateMotion', 'animateTransform', 'circle', 'clipPath', 'defs', 'desc', 'ellipse',
        'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix', 'feDiffuseLighting',
        'feDisplacementMap', 'feDistantLight', 'feDropShadow', 'feFlood', 'feFuncA', 'feFuncB', 'feFuncG', 'feFuncR',
        'feGaussianBlur', 'feImage', 'feMerge', 'feMergeNode', 'feMorphology', 'feOffset', 'fePointLight',
        'feSpecularLighting', 'feSpotLight', 'feTile', 'feTurbulence', 'filter', 'g', 'image', 'line',
        'linearGradient', 'marker', 'mask', 'metadata', 'mpath', 'path', 'pattern', 'polygon', 'polyline',
        'radialGradient', 'rect', 'set', 'stop', 'style', 'svg', 'switch', 'symbol', 'text', 'textPath', 'title',
        'tspan', 'use', 'view',
    ];

    /**
     * The elements that may embed a photo as a data url.
     */
    private const array IMAGES = ['image', 'feImage'];

    /**
     * Refuses an svg that does more than draw. Shown in an img an svg cannot run code, but whoever opens its url
     * runs it on the domain of the site, next to the session of the CMS. So it may hold no script, no event
     * attribute, no html, no processing instruction, no entity, no reference outside the drawing (a photo may be
     * embedded as a data url) and no style that loads something from elsewhere. A drawing that passes is stored
     * exactly as it came in.
     *
     * @throws ValidationException keyed by "file"
     */
    public function handle(string $contents): void
    {
        $document = $this->parse($contents);
        $root = $document->documentElement;

        if ($root === null || $root->localName !== 'svg' || $root->namespaceURI !== self::SVG) {
            throw self::refusal('unreadable');
        }

        // The DOCTYPE drawing programs write is harmless; one with declarations of its own can define entities.
        if (trim((string) $document->doctype?->internalSubset) !== '' || ! $this->draws($document)) {
            throw self::refusal('svg_unsafe');
        }
    }

    private function parse(string $contents): XMLDocument
    {
        $previous = libxml_use_internal_errors(true);

        try {
            // No network and no entity substitution: the parser never reaches outside the file.
            return XMLDocument::createFromString($contents, LIBXML_NONET);
        } catch (DOMException|ValueError) {
            throw self::refusal('unreadable');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function draws(Node $node): bool
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof ProcessingInstruction) {
                return false;
            }

            if ($child instanceof Element && (! $this->drawsItself($child) || ! $this->draws($child))) {
                return false;
            }
        }

        return true;
    }

    private function drawsItself(Element $element): bool
    {
        $svg = $element->namespaceURI === self::SVG;

        // An element of another namespace (the metadata of a drawing program) is inert, except html, whose script runs.
        if ($element->namespaceURI === self::XHTML || ($svg && ! in_array($element->localName, self::ELEMENTS, true))) {
            return false;
        }

        if ($svg && $element->localName === 'style' && ! $this->safeStyle((string) $element->textContent)) {
            return false;
        }

        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->localName);
            $value = strtolower(trim($attribute->value));

            $safe = match (true) {
                str_starts_with($name, 'on') => false,
                $name === 'href' => $this->safeReference($value, $svg && in_array($element->localName, self::IMAGES, true)),
                $name === 'style' => $this->safeStyle($value),
                // An animation could turn a reference or an event attribute into something else.
                $name === 'attributename' => ! str_ends_with($value, 'href') && ! str_starts_with($value, 'on'),
                default => true,
            };

            if (! $safe) {
                return false;
            }
        }

        return true;
    }

    private function safeReference(string $reference, bool $image): bool
    {
        return str_starts_with($reference, '#')
            || ($image && preg_match('#^data:image/(png|jpe?g|gif|webp)[;,]#', $reference) === 1);
    }

    /**
     * A style may refer to a gradient or a filter in the drawing, never load something: no import, no url outside
     * the drawing, no escape that could hide one, and none of the old ways browsers ran code from css.
     */
    private function safeStyle(string $css): bool
    {
        $css = strtolower($css);

        foreach (['\\', '@import', 'expression(', 'javascript:', '-moz-binding', 'behavior:'] as $needle) {
            if (str_contains($css, $needle)) {
                return false;
            }
        }

        return preg_match('/url\(\s*+[\'"]?+\s*+(?!#)/', $css) !== 1;
    }

    private static function refusal(string $error): ValidationException
    {
        return ValidationException::withMessages(['file' => (string) __('made-cms-package::media.errors.'.$error)]);
    }
}
