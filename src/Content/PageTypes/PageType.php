<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\PageTypes;

use Illuminate\Support\Str;

/**
 * A kind of page the site defines, such as a team page: which strip types an editor can add
 * to it and which strips a new page of this kind starts with.
 *
 * The key is stored with every page, so it never changes once a page uses it, also not when
 * the class is renamed. A page without a type is an ordinary page: every strip type, no strips.
 */
abstract class PageType
{
    /**
     * The fixed identifier: lowercase letters, digits and dashes, e.g. "team".
     */
    abstract public function key(): string;

    /**
     * The name editors see when they choose the kind of a page.
     */
    public function label(): string
    {
        return Str::headline(class_basename(static::class));
    }

    /**
     * A short explanation for the choice, if the name is not enough.
     */
    public function description(): ?string
    {
        return null;
    }

    /**
     * The keys of the strip types an editor can add; null allows every strip type.
     *
     * @return list<string>|null
     */
    public function strips(): ?array
    {
        return null;
    }

    /**
     * The keys of the strip types a new page starts with, in order; a key may come back.
     *
     * @return list<string>
     */
    public function defaults(): array
    {
        return [];
    }

    final public function allows(string $stripKey): bool
    {
        $strips = $this->strips();

        return $strips === null || in_array($stripKey, $strips, true);
    }
}
