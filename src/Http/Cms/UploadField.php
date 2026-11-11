<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Actions\DescribeUploadLimits;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;

/**
 * The upload field of the CMS: one field for the media library, the picker and the edit shell. A host includes the
 * partial `cms.partials.upload` with what make() or image() gives as `$upload` and loads its script.
 *
 * @phpstan-type Field array{
 *     t: Closure(string): string,
 *     rules: string,
 *     accept: string,
 *     one_image: bool,
 *     script: string,
 *     config: array{
 *         urls: array{upload: string, alt: string},
 *         csrf_token: string,
 *         folder: array{id: int|null, label: string},
 *         limits: array{max_bytes: int},
 *         one_image: bool,
 *         texts: array<string, string>,
 *     },
 * }
 */
final class UploadField
{
    /**
     * What the partial and the script of the field need. The script gets its config as JSON on the field itself
     * (`data-config`), so a page can hold more than one field: where a file and its alt text go, the folder the
     * files go into, the largest file (the server checks it again) and the texts it fills in.
     *
     * @param  MediaFolder|null  $folder  the folder the files go into; null for none ("Alle bestanden")
     * @return Field
     */
    public static function make(?MediaFolder $folder = null): array
    {
        return self::field($folder, oneImage: false);
    }

    /**
     * The field of an image in a strip, the media element of the side panel: it takes one photo at a time and no
     * document or video, and says so in the singular. The library still checks every file it gets.
     *
     * @param  MediaFolder|null  $folder  the folder the photo goes into; null for none ("Alle bestanden")
     * @return Field
     */
    public static function image(?MediaFolder $folder = null): array
    {
        return self::field($folder, oneImage: true);
    }

    /**
     * @return Field
     */
    private static function field(?MediaFolder $folder, bool $oneImage): array
    {
        $locale = Locale::launcher();
        // The field for one photo speaks in the singular where its texts differ (upload.single).
        $t = static function (string $key, array $replace = []) use ($locale, $oneImage): string {
            $single = 'made-cms-package::media.upload.single.'.$key;

            return (string) __($oneImage && Lang::has($single, $locale) ? $single : 'made-cms-package::media.upload.'.$key, $replace, $locale);
        };
        $limits = DescribeUploadLimits::run();
        $mimes = $oneImage
            ? array_values(array_filter($limits->mimes(), static fn (string $mime): bool => MediaKind::fromMime($mime) === MediaKind::Image))
            : $limits->mimes();
        $kinds = $oneImage ? [MediaKind::Image] : $limits->kinds;
        $base = request()->getBaseUrl();
        $label = $folder === null ? $t('all_files') : $t('folder', ['name' => $folder->name]);
        // The line of rules with the folder left open, so the script can name another folder in it (upload-folder).
        $rules = $t('rules', [
            'kinds' => Str::ucfirst(self::list(
                array_map(static fn (MediaKind $kind): string => $t('kinds.'.$kind->value), $kinds),
                (string) __('made-cms-package::media.and', [], $locale),
            )),
            'max' => $limits->max,
        ]);

        return [
            't' => static fn (string $key): string => $t($key),
            'rules' => str_replace(':folder', $label, $rules),
            'accept' => implode(',', $mimes),
            'one_image' => $oneImage,
            'script' => LauncherAssetController::url('upload.js'),
            'config' => [
                'urls' => [
                    'upload' => $base.route('made-cms-package.media.store', [], false),
                    'alt' => $base.route('made-cms-package.media.alt.update', ['media' => ':media'], false),
                ],
                'csrf_token' => (string) csrf_token(),
                'folder' => ['id' => $folder?->id, 'label' => $label],
                // Only to spare an editor an upload the server refuses anyway; CheckUploadedMedia decides.
                'limits' => ['max_bytes' => $limits->maxBytes],
                'one_image' => $oneImage,
                'texts' => [
                    ...self::texts('upload.texts', $locale),
                    ...($oneImage ? self::texts('upload.single.texts', $locale) : []),
                    'processing_image' => (string) __('made-cms-package::media.upload.texts.processing_image', [
                        'format' => config()->string('made-cms-package.media.image.format'),
                        'width' => config()->integer('made-cms-package.media.image.max_width'),
                    ], $locale),
                    'too_large' => (string) __('made-cms-package::media.errors.too_large', ['max' => $limits->max], $locale),
                    'too_large_video' => (string) __('made-cms-package::media.errors.too_large_video', ['max' => $limits->max], $locale),
                    'server_limit' => (string) __('made-cms-package::media.errors.server_limit', ['max' => self::serverLimit()], $locale),
                    'rules' => $rules,
                    'folder' => $t('folder'),
                    'all_files' => $t('all_files'),
                    'kb' => (string) __('made-cms-package::media.details.kb', [], $locale),
                    'mb' => (string) __('made-cms-package::media.details.mb', [], $locale),
                    'decimal' => (string) __('made-cms-package::media.details.decimal', [], $locale),
                ],
            ],
        ];
    }

    /**
     * The texts under a key of media.upload, as plain strings.
     *
     * @return array<string, string>
     */
    private static function texts(string $key, string $locale): array
    {
        $texts = __('made-cms-package::media.'.$key, [], $locale);

        return is_array($texts) ? array_map(strval(...), $texts) : [];
    }

    /**
     * The largest upload php itself takes (upload_max_filesize and post_max_size), as an editor reads it: "2 MB".
     * Above it a file never reaches the library, whatever media.max_upload_kb says.
     */
    public static function serverLimit(): string
    {
        $kilobytes = intdiv((int) UploadedFile::getMaxFilesize(), 1024);

        return $kilobytes % 1024 === 0 ? intdiv($kilobytes, 1024).' MB' : $kilobytes.' kB';
    }

    /**
     * Names as a sentence lists them: "foto’s, documenten en video’s".
     *
     * @param  list<string>  $names
     */
    private static function list(array $names, string $and): string
    {
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names).' '.$and.' '.$last;
    }
}
