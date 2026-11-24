<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\Strip;

final class CopyStrip
{
    use AsAction;

    /**
     * A strip as it is stored, under an id of its own: its type, its visibility and its values stay as they are. The
     * ids of the items in its lists stay too, because they only have to differ within one strip.
     */
    public function handle(Strip $strip): Strip
    {
        return $strip->withId((string) Str::uuid());
    }
}
