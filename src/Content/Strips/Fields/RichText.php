<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Text with a fixed set of markup: paragraphs, bold, italic, links and lists.
 *
 * It is stored as cleaned HTML and cleaned again when it renders, so every stack prints it as it is. Common text
 * markup outside the set (headings, divs, spans, tables …) loses its tags but keeps its text; everything else,
 * such as scripts, styles and embeds, disappears with its content.
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

    public function type(): string
    {
        return 'rich_text';
    }

    protected function details(): array
    {
        return ['max_length' => self::MAX_LENGTH];
    }

    /**
     * The text without its markup; blocks and line breaks become spaces.
     */
    public function summary(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = strip_tags((string) preg_replace('/<(?:\/?(?:p|li|ul|ol)|br)\b[^>]*>/i', ' ', $value));

        return self::line(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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

    /**
     * The stored HTML cleaned once more, because not every value passed normalize(): a seeder or an import writes the
     * strips directly, a field may have been another type before, and a default is never stored. A value that is not
     * a string renders as nothing. Cleaning stays the last step of rendering, also when links get their address here.
     */
    public function render(mixed $value): ?string
    {
        return is_string($value) ? self::sanitizer()->sanitize($value) : null;
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
            // The validation limits the length. Cleaning makes a text longer (' becomes &#039;), so a limit here
            // would cut the end off a stored text when it renders.
            ->withMaxInputLength(-1);

        foreach (self::KEEP_TEXT_OF as $element) {
            $config = $config->blockElement($element);
        }

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
