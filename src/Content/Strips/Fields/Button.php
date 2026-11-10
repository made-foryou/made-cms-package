<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

use Made\CmsPackage\Content\Data\MediaFiles;

/**
 * A button: a text and a link, stored as {"text": "…", "link": {…}}.
 */
final class Button extends Field
{
    public static function make(string $key): self
    {
        return new self($key);
    }

    public function type(): string
    {
        return 'button';
    }

    protected function details(): array
    {
        return [
            'parts' => [
                'text' => (string) __('made-cms-package::strips.button.text'),
                'link' => (string) __('made-cms-package::strips.button.link'),
            ],
        ];
    }

    public function summary(mixed $value): ?string
    {
        return is_array($value) ? self::line($value['text'] ?? null) : null;
    }

    public function rules(string $path): array
    {
        return [
            $path => [$this->presence(), 'array:text,link'],
            "{$path}.text" => ["required_with:{$path}", 'string', 'max:'.Text::MAX_LENGTH],
            "{$path}.link" => ["required_with:{$path}", 'array:page,url', Link::target()],
        ];
    }

    public function attributes(string $path): array
    {
        return [
            $path => $this->getLabel(),
            "{$path}.text" => (string) __('made-cms-package::strips.button.text'),
            "{$path}.link" => (string) __('made-cms-package::strips.button.link'),
        ];
    }

    public function normalize(mixed $value): mixed
    {
        if (! is_array($value) || ! array_key_exists('link', $value)) {
            return $value;
        }

        $value['link'] = Link::normalizeTarget($value['link']);

        return $value;
    }

    /**
     * A button as its text and url; without a text or a url there is no button to show.
     *
     * @return array{text: string, url: string}|null
     */
    public function render(mixed $value, ?MediaFiles $media = null): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $text = $value['text'] ?? null;
        $url = Link::resolve($value['link'] ?? null);

        if (! is_string($text) || $text === '' || $url === null) {
            return null;
        }

        return ['text' => $text, 'url' => $url];
    }
}
