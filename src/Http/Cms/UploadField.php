<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Actions\DescribeUploadLimits;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;

/**
 * The upload field of the CMS: one field for the media library, the picker and the edit shell. A host includes the
 * partial `cms.partials.upload` with what make() gives as `$upload` and loads its script.
 */
final class UploadField
{
    /**
     * What the partial and the script of the field need. The script gets its config as JSON on the field itself
     * (`data-config`), so a page can hold more than one field: where a file and its alt text go, the folder the
     * files go into, the largest file (the server checks it again) and the texts it fills in.
     *
     * @param  MediaFolder|null  $folder  the folder the files go into; null for none ("Alle bestanden")
     * @return array{
     *     t: Closure(string): string,
     *     rules: string,
     *     accept: string,
     *     script: string,
     *     config: array{
     *         urls: array{upload: string, alt: string},
     *         csrf_token: string,
     *         folder: array{id: int|null, label: string},
     *         limits: array{max_bytes: int},
     *         texts: array<string, string>,
     *     },
     * }
     */
    public static function make(?MediaFolder $folder = null): array
    {
        $locale = Locale::launcher();
        $t = static fn (string $key, array $replace = []): string => (string) __('made-cms-package::media.'.$key, $replace, $locale);
        $limits = DescribeUploadLimits::run();
        $base = request()->getBaseUrl();
        $label = $folder === null ? $t('upload.all_files') : $t('upload.folder', ['name' => $folder->name]);

        /** @var array<string, string> $texts */
        $texts = array_map(strval(...), (array) __('made-cms-package::media.upload.texts', [], $locale));

        return [
            't' => static fn (string $key): string => $t('upload.'.$key),
            'rules' => $t('upload.rules', [
                'kinds' => Str::ucfirst(self::list(
                    array_map(static fn (MediaKind $kind): string => $t('upload.kinds.'.$kind->value), $limits->kinds),
                    $t('and'),
                )),
                'max' => $limits->max,
                'folder' => $label,
            ]),
            'accept' => implode(',', $limits->mimes()),
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
                'texts' => [
                    ...$texts,
                    'processing_image' => $t('upload.texts.processing_image', [
                        'format' => config()->string('made-cms-package.media.image.format'),
                        'width' => config()->integer('made-cms-package.media.image.max_width'),
                    ]),
                    'too_large' => $t('errors.too_large', ['max' => $limits->max]),
                    'too_large_video' => $t('errors.too_large_video', ['max' => $limits->max]),
                    'server_limit' => $t('errors.server_limit', ['max' => self::serverLimit()]),
                    'kb' => $t('details.kb'),
                    'mb' => $t('details.mb'),
                    'decimal' => $t('details.decimal'),
                ],
            ],
        ];
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
