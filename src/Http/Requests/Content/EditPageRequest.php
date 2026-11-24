<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The address of the edit shell: the strip it opens with chosen (strip), as "Bewerken" in the card "Wat erop staat"
 * links it. A screen never refuses its address: a strip it does not know opens the structure.
 */
final class EditPageRequest extends LauncherRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The id of the strip to choose; null without one.
     */
    public function chosenStrip(): ?string
    {
        $strip = $this->query('strip');

        return is_string($strip) && $strip !== '' ? $strip : null;
    }
}
