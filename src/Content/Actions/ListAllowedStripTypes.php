<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\PageTypes\PageTypes;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Content\Strips\StripTypes;

final class ListAllowedStripTypes
{
    use AsAction;

    public function __construct(
        private readonly StripTypes $strips,
        private readonly PageTypes $pages,
    ) {}

    /**
     * The strip types an editor can add to a page of a type, in the order of the catalogue. An ordinary page takes
     * every strip type, and so does a page whose type the site no longer defines, so its editor is never stuck.
     *
     * @param  string|null  $pageType  the key of the type of the page; null for an ordinary page
     * @return list<StripType>
     */
    public function handle(?string $pageType): array
    {
        if ($pageType === null || ! $this->pages->has($pageType)) {
            return $this->strips->all();
        }

        $type = $this->pages->find($pageType);

        return array_values(array_filter($this->strips->all(), static fn (StripType $strip): bool => $type->allows($strip->key())));
    }
}
