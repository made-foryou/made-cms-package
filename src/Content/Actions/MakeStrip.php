<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\Strip;
use Made\CmsPackage\Content\Strips\StripType;

final class MakeStrip
{
    use AsAction;

    /**
     * A new strip of a type, visible, with an id of its own and the defaults of the type in their stored form.
     */
    public function handle(StripType $type): Strip
    {
        return new Strip((string) Str::uuid(), $type->key(), true, $type->normalize($type->defaults()));
    }
}
