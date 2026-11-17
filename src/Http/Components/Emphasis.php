<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\Component;
use Made\CmsPackage\Content\Strips\Emphasis as EmphasisRule;

/**
 * <h2 data-cms-field="kop"><x-made-cms::emphasis :text="$strip->kop" /></h2>: the text of a field with emphasis(),
 * escaped, with the words between asterisks as an <em>; nothing without a text.
 *
 * It prints only the text, without an element or whitespace of its own, so the element of the site around it keeps
 * data-cms-field and its classes. A heading prints it with <x-made-cms::heading … emphasis />.
 */
final class Emphasis extends Component implements Htmlable
{
    public function __construct(
        public readonly ?string $text = null,
    ) {}

    /**
     * The text escaped, with every part with emphasis in an <em>.
     */
    public static function html(string $text): string
    {
        $html = '';

        foreach (EmphasisRule::parts($text) as $part) {
            $html .= $part['emphasis'] ? '<em>'.e($part['text']).'</em>' : e($part['text']);
        }

        return $html;
    }

    public function shouldRender(): bool
    {
        return $this->text !== null && $this->text !== '';
    }

    public function render(): Htmlable
    {
        return $this;
    }

    public function toHtml(): string
    {
        return self::html((string) $this->text);
    }
}
