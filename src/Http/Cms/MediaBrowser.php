<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Closure;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;

/**
 * The list of the media library with its folders, the same in the picker and the media library: the partial
 * `cms.partials.media-browser` with what make() gives as `$browser`, and its script. The picker of an image puts it in
 * a dialog (picker(), the partial `cms.partials.media-picker`).
 *
 * @phpstan-import-type Field from UploadField
 *
 * @phpstan-type Browser array{
 *     t: Closure(string): string,
 *     kind: string|null,
 *     upload: Field|null,
 *     script: string,
 *     config: array{
 *         urls: array{files: string, folders: string, chosen_folder: string},
 *         csrf_token: string,
 *         kind: string|null,
 *         folder: array{id: int, name: string}|null,
 *         texts: array<string, string>,
 *     },
 * }
 * @phpstan-type Picker array{t: Closure(string): string, browser: Browser, config: array{texts: array<string, string>}}
 */
final class MediaBrowser
{
    /**
     * What the partial and the script of the list need. The script gets its config as JSON on the list itself
     * (`data-config`): where it lists the files and the folders and remembers the folder an editor opens, the kind it
     * shows and the folder it opens in.
     *
     * @param  MediaFolder|null  $folder  the folder the list opens in; null for "Alle bestanden"
     * @param  MediaKind|null  $kind  the only kind the list shows; null for every kind
     * @param  Field|null  $upload  the upload field below the folders, which follows the folder the editor opens
     * @return Browser
     */
    public static function make(?MediaFolder $folder = null, ?MediaKind $kind = null, ?array $upload = null): array
    {
        $locale = Locale::launcher();
        $t = self::translator('browser', $locale);
        $base = request()->getBaseUrl();

        return [
            't' => $t,
            'kind' => $kind === null ? null : $t('kinds.'.$kind->value),
            'upload' => $upload,
            'script' => LauncherAssetController::url('media.js'),
            'config' => [
                'urls' => [
                    'files' => $base.route('made-cms-package.media.files', [], false),
                    'folders' => $base.route('made-cms-package.media.folders', [], false),
                    'chosen_folder' => $base.route('made-cms-package.media.chosen-folder.update', [], false),
                ],
                'csrf_token' => (string) csrf_token(),
                'kind' => $kind?->value,
                'folder' => $folder === null ? null : ['id' => $folder->id, 'name' => $folder->name],
                'texts' => self::texts('browser.texts', $locale),
            ],
        ];
    }

    /**
     * The picker of an image: the list of the images in a dialog, with the upload field of one photo below the
     * folders. It opens in the folder the editor chose last, and the upload field puts a photo there.
     *
     * @return Picker
     */
    public static function picker(?MediaFolder $folder = null): array
    {
        $locale = Locale::launcher();

        return [
            't' => self::translator('picker', $locale),
            'browser' => self::make($folder, MediaKind::Image, UploadField::image($folder)),
            'config' => ['texts' => self::texts('picker.texts', $locale)],
        ];
    }

    /**
     * @return Closure(string): string
     */
    private static function translator(string $group, string $locale): Closure
    {
        return static fn (string $key): string => (string) __('made-cms-package::media.'.$group.'.'.$key, [], $locale);
    }

    /**
     * @return array<string, string>
     */
    private static function texts(string $key, string $locale): array
    {
        $texts = __('made-cms-package::media.'.$key, [], $locale);

        return is_array($texts) ? array_map(strval(...), $texts) : [];
    }
}
