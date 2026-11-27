<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Requests\Content;

use Made\CmsPackage\Http\Requests\LauncherRequest;

/**
 * The address of the edit shell: the strip it opens with chosen (strip), as "Bewerken" in the card "Wat erop staat"
 * links it, and the overview Koppen (view=headings), as the attention point of more than one h1 links it. A screen
 * never refuses its address: a strip it does not know, or a view it does not have, opens the structure.
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

    /**
     * Whether the shell opens at the overview Koppen.
     */
    public function opensHeadings(): bool
    {
        return $this->query('view') === 'headings';
    }
}
