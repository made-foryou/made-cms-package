<?php

declare(strict_types=1);

namespace Made\CmsPackage\Content\Actions;

use finfo;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Made\CmsPackage\Content\Data\CheckedMedia;
use Made\CmsPackage\Content\Enums\MediaKind;

final class CheckUploadedMedia
{
    use AsAction;

    private const string SVG = 'image/svg+xml';

    /**
     * The types an svg is read as when something comes before its root, such as the comment of the program that
     * made it.
     */
    private const array TEXT = ['text/plain', 'text/xml', 'application/xml'];

    /**
     * Reads what an upload is from its contents, never from its name, and refuses it when the library does not take
     * it: larger than media.max_upload_kb, of a type outside media.allowed_mimes, or an svg that does more than draw.
     * The size goes first, so a video that is too large gets the advice for videos whatever its type.
     *
     * @throws ValidationException keyed by "file"
     */
    public function handle(string $path): CheckedMedia
    {
        $size = is_file($path) && is_readable($path) ? filesize($path) : false;
        $mime = $size === false ? false : (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($size === false || $mime === false) {
            throw self::refusal('unreadable');
        }

        $limits = DescribeUploadLimits::run();

        if ($size > $limits->maxBytes) {
            throw self::refusal(MediaKind::fromMime($mime) === MediaKind::Video ? 'too_large_video' : 'too_large', ['max' => $limits->max]);
        }

        if (in_array($mime, self::TEXT, true) && $this->startsAsSvg((string) file_get_contents($path, length: 65536))) {
            $mime = self::SVG;
        }

        $extension = $limits->extension($mime);

        if ($extension === null) {
            throw self::refusal('type_unknown', ['types' => $limits->types]);
        }

        if ($mime === self::SVG) {
            CheckSvg::run((string) file_get_contents($path));
        }

        return new CheckedMedia($mime, MediaKind::fromMime($mime), $extension, $size);
    }

    /**
     * Whether the root of the text is an svg element, after an XML declaration, comments and a DOCTYPE.
     */
    private function startsAsSvg(string $text): bool
    {
        return preg_match('/\A(?:\xEF\xBB\xBF)?\s*+(?:<\?xml[^>]*+>\s*+)?(?:(?:<!--.*?-->|<!DOCTYPE[^>\[]*+>)\s*+)*<svg[\s>\/]/s', $text) === 1;
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function refusal(string $error, array $replace = []): ValidationException
    {
        return ValidationException::withMessages(['file' => (string) __('made-cms-package::media.errors.'.$error, $replace)]);
    }
}
