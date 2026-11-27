<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\MetaLength;
use Made\CmsPackage\Content\Enums\MetaField;

final class MeasureMeta
{
    use AsAction;

    /**
     * Measures a title or a description as Google gets it, on one line. The counter of the card in `cms.js` counts the
     * same way: characters, not bytes.
     */
    public function handle(MetaField $field, ?string $text): MetaLength
    {
        $length = mb_strlen(Str::squish((string) $text));
        $tooLong = $length > $field->limit();

        return new MetaLength(
            length: $length,
            limit: $field->limit(),
            missing: $length === 0,
            tooLong: $tooLong,
            nearLimit: ! $tooLong && $length > $field->warnAbove(),
        );
    }
}
