<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Text with a fixed set of markup: paragraphs, bold, italic, links and lists.
 *
 * It is stored as cleaned HTML, so every stack renders it as it is. Common text markup
 * outside the set (headings, divs, spans, tables …) loses its tags but keeps its text;
 * everything else, such as scripts, styles and embeds, disappears with its content.
 */
final class RichText extends Field
{
    public const int MAX_LENGTH = 65535;

    /**
     * Elements whose tags are removed while their text stays, e.g. when pasting from Word or another site.
     */
    private const array KEEP_TEXT_OF = [
        'abbr', 'address', 'article', 'aside', 'b', 'blockquote', 'br', 'caption', 'center', 'cite',
        'code', 'dd', 'div', 'dl', 'dt', 'figcaption', 'figure', 'font', 'footer', 'h1', 'h2', 'h3',
        'h4', 'h5', 'h6', 'header', 'i', 'label', 'main', 'mark', 'nav', 'pre', 'q', 's', 'section',
        'small', 'span', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'time', 'tr', 'u',
    ];

    private static ?HtmlSanitizer $sanitizer = null;

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function rules(string $path): array
    {
        return [$path => [$this->presence(), 'string', 'max:'.self::MAX_LENGTH]];
    }

    public function normalize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return self::sanitizer()->sanitize($value);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer instanceof HtmlSanitizer) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->defaultAction(HtmlSanitizerAction::Drop)
            ->allowElement('p')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->withMaxInputLength(self::MAX_LENGTH);

        foreach (self::KEEP_TEXT_OF as $element) {
            $config = $config->blockElement($element);
        }

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
