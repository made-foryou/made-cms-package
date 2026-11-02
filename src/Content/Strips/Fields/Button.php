<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Strips\Fields;

/**
 * A button: a text and a link, stored as {"text": "…", "link": {…}}.
 */
final class Button extends Field
{
    public static function make(string $key): self
    {
        return new self($key);
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
}
