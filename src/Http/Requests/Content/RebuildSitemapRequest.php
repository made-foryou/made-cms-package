<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Content\Data\SitemapReason;
use Made\CmsPackage\Content\Enums\SitemapCause;
use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * "Opnieuw genereren" on the screen of the sitemap, with the route group the screen showed (groep).
 */
final class RebuildSitemapRequest extends LauncherRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function toData(): SitemapReason
    {
        return new SitemapReason(SitemapCause::Manual, editorId: $this->editorId());
    }

    /**
     * The route group the screen showed, "" at the root.
     */
    public function group(): string
    {
        $group = $this->input('groep');

        return is_string($group) ? trim($group, '/') : '';
    }
}
