<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\StripLookup;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The strips the edit mode found on a page: only their ids, read from data-made-cms-strip.
 */
final class ShowStripsRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['required', 'integer', 'min:1'],
        ];
    }

    public function toData(): StripLookup
    {
        /** @var array<array-key, int|string> $ids */
        $ids = (array) $this->input('ids', []);

        return new StripLookup(ids: array_values(array_unique(array_map(intval(...), $ids))));
    }
}
