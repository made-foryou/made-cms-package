<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\Component;
use Illuminate\View\ComponentAttributeBag;
use Made\CmsPackage\Content\Strips\Fields\Heading as HeadingField;

/**
 * <x-made-cms::heading :heading="$strip->kop" field="kop" class="hero__titel" />: a heading as the element of its
 * level, with data-cms-field for the edit shell and the other attributes of the site; nothing without a text.
 *
 * The element follows the level an editor chose, so the site gives a heading its look through a class, never through
 * its tag. It is one element, built when Blade renders it: only then does the component hold its attributes.
 * For a field with emphasis() the view adds emphasis, and the words between asterisks become an <em>.
 */
final class Heading extends Component implements Htmlable
{
    /**
     * @param  array<array-key, mixed>|null  $heading  the rendered value of a Heading field: {text, tag} or null
     * @param  string|null  $field  the field it shows, for data-cms-field; in a list the field of the item ("titel")
     * @param  bool  $emphasis  whether the field has emphasis(): the words between asterisks are an <em>
     */
    public function __construct(
        public readonly ?array $heading = null,
        public readonly ?string $field = null,
        public readonly bool $emphasis = false,
    ) {}

    public function shouldRender(): bool
    {
        return $this->text() !== null;
    }

    public function render(): Htmlable
    {
        return $this;
    }

    /**
     * The element of the level, or a span for a value that is no level: the tag is printed as it is, so it only
     * ever comes from the list of levels.
     */
    public function toHtml(): string
    {
        $tag = in_array($this->heading['tag'] ?? null, HeadingField::TAGS, true) ? $this->heading['tag'] : 'span';
        $field = $this->field === null ? [] : ['data-cms-field' => e($this->field)];
        $attributes = (string) new ComponentAttributeBag([...$field, ...$this->attributes->getAttributes()]);

        $text = (string) $this->text();

        return '<'.$tag.($attributes === '' ? '' : ' '.$attributes).'>'.($this->emphasis ? Emphasis::html($text) : e($text)).'</'.$tag.'>';
    }

    private function text(): ?string
    {
        $text = $this->heading['text'] ?? null;

        return is_string($text) && $text !== '' ? $text : null;
    }
}
