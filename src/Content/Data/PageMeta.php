<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Data;

/**
 * What the head of a page tells Google: its title, its description, its canonical address and whether Google may show it.
 */
final readonly class PageMeta
{
    /**
     * @param  string  $title  the title of its own, else the fallback (MakeFallbackTitle)
     * @param  string|null  $description  null leaves the description to Google
     * @param  string|null  $canonical  the absolute address from the url of the site; null for a page without an address
     * @param  bool  $indexable  false is noindex
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?string $canonical,
        public bool $indexable,
    ) {}

    /**
     * The meta as a prop for Inertia, Vue or React.
     *
     * @return array{title: string, description: string|null, canonical: string|null, indexable: bool}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'indexable' => $this->indexable,
        ];
    }
}
