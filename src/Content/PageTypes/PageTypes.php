<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\PageTypes;

use Illuminate\Container\Container;
use Made\CmsPackage\Content\Exceptions\DuplicatePageType;
use Made\CmsPackage\Content\Exceptions\InvalidPageType;
use Made\CmsPackage\Content\Exceptions\UnknownPageType;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Support\ClassesInFolder;

/**
 * The catalogue of page types the site defines; editors choose one for a new page.
 *
 * Every definition is checked against the strip types when the catalogue is built, so a
 * mistake in a page type shows up at once instead of when an editor first uses it.
 */
final class PageTypes
{
    /**
     * @var array<string, PageType>
     */
    private array $types = [];

    /**
     * @param  list<PageType>  $types
     */
    public function __construct(array $types, StripTypes $strips)
    {
        foreach ($types as $type) {
            self::check($type, $strips);

            $key = $type->key();

            if (isset($this->types[$key])) {
                throw DuplicatePageType::key($key, $this->types[$key]::class, $type::class);
            }

            $this->types[$key] = $type;
        }

        uasort($this->types, static fn (PageType $a, PageType $b): int => strnatcasecmp($a->label(), $b->label()) ?: strcmp($a->key(), $b->key()));
    }

    /**
     * Builds the catalogue from the page type classes in a folder and its sub-folders (PSR-4).
     */
    public static function discover(string $path, string $namespace, StripTypes $strips): self
    {
        $container = Container::getInstance();

        return new self(array_map(
            static fn (string $class): PageType => $container->make($class),
            ClassesInFolder::find($path, $namespace, PageType::class),
        ), $strips);
    }

    /**
     * Every page type, sorted by label.
     *
     * @return list<PageType>
     */
    public function all(): array
    {
        return array_values($this->types);
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * @throws UnknownPageType
     */
    public function find(string $key): PageType
    {
        return $this->types[$key] ?? throw UnknownPageType::key($key);
    }

    /**
     * @throws InvalidPageType
     */
    private static function check(PageType $type, StripTypes $strips): void
    {
        if (preg_match(StripTypes::KEY_PATTERN, $type->key()) !== 1) {
            throw InvalidPageType::key($type::class, $type->key());
        }

        $allowed = $type->strips();

        if ($allowed === []) {
            throw InvalidPageType::noStrips($type::class);
        }

        foreach ([...$allowed ?? [], ...$type->defaults()] as $strip) {
            if (! $strips->has($strip)) {
                throw InvalidPageType::unknownStrip($type::class, $strip);
            }
        }

        foreach ($type->defaults() as $strip) {
            if (! $type->allows($strip)) {
                throw InvalidPageType::defaultNotAllowed($type::class, $strip);
            }
        }
    }
}
