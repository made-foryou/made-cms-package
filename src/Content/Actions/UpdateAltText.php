<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Events\AltTextUpdated;
use Made\CmsPackage\Content\Models\Media;

final class UpdateAltText
{
    use AsAction;

    public const int MAX_LENGTH = 255;

    /**
     * Gives a file the alt text that holds wherever it is used; for a document it is the description of its download
     * link. A blank text takes it away, so the file shows up as an image without one.
     *
     * @throws ValidationException keyed by "alt" for a text longer than MAX_LENGTH characters
     */
    public function handle(Media $media, ?string $alt): Media
    {
        $alt = trim((string) $alt);
        $alt = $alt === '' ? null : $alt;

        if ($alt !== null && mb_strlen($alt) > self::MAX_LENGTH) {
            throw ValidationException::withMessages([
                'alt' => (string) __('made-cms-package::media.errors.alt_long', ['max' => self::MAX_LENGTH]),
            ]);
        }

        if ($media->alt === $alt) {
            return $media;
        }

        $media->alt = $alt;
        $media->save();

        AltTextUpdated::dispatch($media);

        return $media;
    }
}
