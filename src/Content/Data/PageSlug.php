<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Illuminate\Support\Str;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;

/**
 * The last segment of the address of a page: lowercase letters, digits and dashes, made from what an editor typed.
 */
final readonly class PageSlug
{
    private function __construct(public string $value) {}

    /**
     * @throws InvalidPageSlug when nothing of the text remains
     */
    public static function fromString(string $raw): self
    {
        $slug = Str::slug($raw);

        if ($slug === '') {
            throw InvalidPageSlug::for($raw);
        }

        return new self($slug);
    }
}
