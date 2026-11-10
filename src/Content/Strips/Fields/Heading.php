<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Illuminate\Validation\Rule;

/**
 * A heading: a text with its level, stored and rendered as {"text": "…", "tag": "h2"}.
 *
 * The level is the element the site prints, h1 to h6, or "span" for a text that is no heading for search engines.
 * The developer gives the level of the design (tag(), h2 by default) and may limit the levels to choose from (tags()).
 * A text stored before the field became a heading (a former Text) counts as that text with the default level.
 */
final class Heading extends Field
{
    /**
     * Every level a heading can have, in order.
     */
    public const array TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span'];

    private string $tag = 'h2';

    /**
     * @var list<string>
     */
    private array $tags = self::TAGS;

    public static function make(string $key): self
    {
        return new self($key);
    }

    /**
     * The level of the design: what a new heading, or a text without a level, gets.
     */
    public function tag(string $tag): self
    {
        $field = clone $this;
        $field->tag = $tag;

        return $field;
    }

    /**
     * The levels an editor may choose from.
     *
     * @param  list<string>  $tags
     */
    public function tags(array $tags): self
    {
        $field = clone $this;
        $field->tags = array_values(array_unique($tags));

        return $field;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    /**
     * The levels to choose from in the order of TAGS; a level that is no tag comes last, for the catalogue to refuse.
     *
     * @return list<string>
     */
    public function getTags(): array
    {
        $order = array_flip(self::TAGS);
        $tags = $this->tags;

        usort($tags, static fn (string $a, string $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return $tags;
    }

    /**
     * The default as a text with its level, also when it is given as a text, so it does not matter whether
     * default() comes before or after tag().
     */
    public function getDefault(): mixed
    {
        return $this->normalize($this->default);
    }

    public function type(): string
    {
        return 'heading';
    }

    /**
     * The levels as a list of {value, label}, the default level and the label of the text in the side panel.
     */
    protected function details(): array
    {
        return [
            'max_length' => Text::MAX_LENGTH,
            'tag' => $this->tag,
            'tags' => array_map(static fn (string $tag): array => [
                'value' => $tag,
                'label' => (string) __('made-cms-package::strips.heading.tags.'.$tag),
            ], $this->getTags()),
            'parts' => [
                'text' => (string) __('made-cms-package::strips.heading.text'),
            ],
        ];
    }

    public function summary(mixed $value): ?string
    {
        return self::line(is_array($value) ? ($value['text'] ?? null) : $value);
    }

    public function rules(string $path): array
    {
        return [
            $path => [$this->presence(), 'array:text,tag'],
            "{$path}.text" => [$this->required ? "required_with:{$path}" : 'nullable', 'string', 'max:'.Text::MAX_LENGTH],
            "{$path}.tag" => ['nullable', 'string', Rule::in($this->getTags())],
        ];
    }

    public function attributes(string $path): array
    {
        return [
            $path => $this->getLabel(),
            "{$path}.text" => $this->getLabel(),
            "{$path}.tag" => (string) __('made-cms-package::strips.heading.tag'),
        ];
    }

    /**
     * Exactly a text and a level: a former text gets the default level, and so does a level that is missing,
     * unknown or no longer one of the levels to choose from. Without a text, a heading at the default level is
     * nothing (null); another level stays, so a level chosen before the text is typed is kept.
     */
    public function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = ['text' => $value];
        }

        if (! is_array($value)) {
            return $value;
        }

        $text = is_string($value['text'] ?? null) && $value['text'] !== '' ? $value['text'] : null;
        $tag = $value['tag'] ?? null;
        $tag = is_string($tag) && in_array($tag, $this->tags, true) && in_array($tag, self::TAGS, true) ? $tag : $this->tag;

        return $text === null && $tag === $this->tag ? null : ['text' => $text, 'tag' => $tag];
    }

    /**
     * The heading as its text and level; without a text there is no heading to show.
     *
     * @return array{text: string, tag: string}|null
     */
    public function render(mixed $value): ?array
    {
        $value = $this->normalize($value);

        if (! is_array($value) || $value['text'] === null) {
            return null;
        }

        return ['text' => $value['text'], 'tag' => $value['tag']];
    }
}
