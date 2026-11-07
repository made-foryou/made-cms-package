<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips;

use Dom\Element;
use Dom\Node;
use Symfony\Component\HtmlSanitizer\Parser\NativeParser;
use Symfony\Component\HtmlSanitizer\Parser\ParserInterface;

/**
 * Reads rich text for the sanitizer of RichText and takes out what the sanitizer cannot handle on its own.
 *
 * Markup nested deeper than MAX_DEPTH keeps only its text: the sanitizer follows the tree recursively, and a few
 * thousand levels exhaust the stack of PHP. A link starting with two slashes leads to another site while it looks like
 * an address on the site, so it loses its address.
 */
final readonly class RichTextParser implements ParserInterface
{
    public const int MAX_DEPTH = 100;

    private NativeParser $parser;

    public function __construct()
    {
        $this->parser = new NativeParser;
    }

    public function parse(string $html, string $context = 'body'): ?Node
    {
        $root = $this->parser->parse($html, $context);

        if (! $root instanceof Node) {
            return null;
        }

        // Walks the tree with a list of its own instead of recursion, for the same reason as the limit.
        $open = [[$root, 0]];

        while ($open !== []) {
            [$node, $depth] = array_pop($open);
            $child = $node->firstChild;

            while ($child instanceof Node) {
                $next = $child->nextSibling;

                if ($child instanceof Element && $depth >= self::MAX_DEPTH) {
                    // Unwrapped from the top, so only empty elements are removed: PHP frees a removed tree
                    // recursively too. Its children take its place and are looked at next.
                    $next = $child->firstChild ?? $next;
                    $child->replaceWith(...iterator_to_array($child->childNodes));
                } elseif ($child instanceof Element) {
                    if ($child->localName === 'a' && preg_match('~^[\x00-\x20]*[/\\\\]{2}~', $child->getAttribute('href') ?? '') === 1) {
                        $child->removeAttribute('href');
                    }

                    $open[] = [$child, $depth + 1];
                }

                $child = $next;
            }
        }

        return $root;
    }
}
