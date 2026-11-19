<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

use Illuminate\Support\Str;
use Made\CmsPackage\Content\Exceptions\InvalidPageSlug;
use Made\CmsPackage\Content\Exceptions\PageSlugTooLong;

/**
 * The last segment of the address of a page: lowercase letters, digits and dashes, made from what an editor typed.
 */
final readonly class PageSlug
{
    /**
     * Counted once the slug is made: a letter can grow in it ("щ", "ß"), so a text within this length may still give
     * a longer slug.
     */
    public const int MAX_LENGTH = 120;

    private function __construct(public string $value) {}

    /**
     * @throws InvalidPageSlug when nothing of the text remains
     * @throws PageSlugTooLong when the slug is longer than MAX_LENGTH
     */
    public static function fromString(string $raw): self
    {
        $slug = Str::slug($raw);

        if ($slug === '') {
            throw InvalidPageSlug::for($raw);
        }

        if (mb_strlen($slug) > self::MAX_LENGTH) {
            throw PageSlugTooLong::for($slug, self::MAX_LENGTH);
        }

        return new self($slug);
    }
}
