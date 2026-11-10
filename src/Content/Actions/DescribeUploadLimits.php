<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\UploadLimits;
use Made\CmsPackage\Content\Enums\MediaKind;
use Symfony\Component\Mime\MimeTypes;

final class DescribeUploadLimits
{
    use AsAction;

    /**
     * What the library takes: the largest file (media.max_upload_kb) and the types (media.allowed_mimes), in numbers
     * for the check and in words for an editor. A type no extension goes with is never taken; the words still name
     * it, so the developer sees what the list holds.
     */
    public function handle(): UploadLimits
    {
        $maximum = config()->integer('made-cms-package.media.max_upload_kb');
        $mimes = array_values(array_unique(array_filter(config()->array('made-cms-package.media.allowed_mimes'), is_string(...))));
        $extensions = [];

        foreach ($mimes as $mime) {
            $extension = MimeTypes::getDefault()->getExtensions($mime)[0] ?? null;

            if ($extension !== null) {
                $extensions[$mime] = $extension;
            }
        }

        return new UploadLimits(
            maxBytes: $maximum * 1024,
            max: $maximum % 1024 === 0 ? intdiv($maximum, 1024).' MB' : $maximum.' kB',
            extensions: $extensions,
            types: $this->types(array_map(static fn (string $mime): string => $extensions[$mime] ?? $mime, $mimes)),
            kinds: array_values(array_unique(array_map(MediaKind::fromMime(...), array_keys($extensions)), SORT_REGULAR)),
        );
    }

    /**
     * The types as their extensions, such as "jpg, png of svg".
     *
     * @param  list<string>  $names
     */
    private function types(array $names): string
    {
        $names = array_values(array_unique($names));
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names).' '.__('made-cms-package::media.or').' '.$last;
    }
}
