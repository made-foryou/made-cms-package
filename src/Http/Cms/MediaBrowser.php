<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Closure;
use Made\CmsPackage\Content\Actions\CheckMediaFolderName;
use Made\CmsPackage\Content\Data\MediaLibrary;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Http\Responses\MediaResponse;
use Made\CmsPackage\Support\Locale;

/**
 * The list of the media library with its folders, the same in the picker and the media library: the partial
 * `cms.partials.media-browser` with what make() gives as `$browser`, and its script. The picker of an image puts it in
 * a dialog (picker(), the partial `cms.partials.media-picker`); the media library on a screen of its own (library()).
 *
 * @phpstan-import-type Field from UploadField
 *
 * @phpstan-type Browser array{
 *     t: Closure(string): string,
 *     kind: string|null,
 *     upload: Field|null,
 *     upload_many: Field|null,
 *     upload_files: array<string, Field>,
 *     library: bool,
 *     script: string,
 *     config: array{
 *         urls: array{files: string, folders: string, chosen_folder: string, folders_store?: string, folder?: string, move_many?: string, delete?: string},
 *         csrf_token: string,
 *         kind: string|null,
 *         folder: array{id: int, name: string}|null,
 *         texts: array<string, string>,
 *         library?: true,
 *         folder_max?: int,
 *         kinds?: array<string, string>,
 *         modes?: array<string, array{kind: string, empty: string}>,
 *         filter?: array{kind: string|null, without_alt: bool, q: string, sort: string},
 *         chosen?: int|null,
 *         initial?: array{files: array<string, mixed>, folders: array<string, mixed>},
 *     },
 * }
 * @phpstan-type Picker array{t: Closure(string): string, browser: Browser, config: array{texts: array<string, string>, modes: array<string, array<string, string>>}}
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
     * @param  Field|null  $uploadMany  in the picker, the upload field of several photos next to it, shown instead
     *                                  when the picker opens from an item of a list
     * @param  array<string, Field>  $uploadFiles  in the picker, the upload field of one file per stand of a file field
     *                                             (document, video, file), shown instead when it opens for one
     * @return Browser
     */
    public static function make(?MediaFolder $folder = null, ?MediaKind $kind = null, ?array $upload = null, ?array $uploadMany = null, array $uploadFiles = []): array
    {
        $locale = Locale::launcher();
        $t = self::translator('browser', $locale);
        $base = request()->getBaseUrl();

        return [
            't' => $t,
            'kind' => $kind === null ? null : $t('kinds.'.$kind->value),
            'upload' => $upload,
            'upload_many' => $uploadMany,
            'upload_files' => $uploadFiles,
            'library' => false,
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
     * The picker of the side panel: the list in a dialog, which shows only what the field it opens for takes. For an
     * image the images, with the upload field of one photo below the folders, or the upload field of several photos
     * when it opens from an item of a list; a photo goes to the crop step once it is in. For a file field
     * (made-cms:pick {kinds}) its stand: the documents, the videos or both, with the upload field of one file of those
     * kinds. It opens in the folder the editor chose last, and the upload field puts a file there.
     *
     * The texts that differ per stand come along as `modes`: the title, the hint and the button of the dialog in its
     * config, the fixed filter and the empty list in the config of the list.
     *
     * @return Picker
     */
    public static function picker(?MediaFolder $folder = null): array
    {
        $locale = Locale::launcher();
        $files = array_filter([
            'document' => UploadField::file($folder, MediaKind::Document),
            'video' => UploadField::file($folder, MediaKind::Video),
            'file' => UploadField::file($folder, MediaKind::Document, MediaKind::Video),
        ]);

        $browser = self::make($folder, MediaKind::Image, UploadField::image($folder, crop: true), UploadField::images($folder, crop: true), $files);
        // The list says what a picker opened from an item of a list selects, beside its button and below its tiles.
        $browser['config']['texts'] = [...$browser['config']['texts'], ...self::texts('picker.list', $locale)];
        $t = self::translator('picker', $locale);
        $browser['config']['modes'] = ['image' => ['kind' => (string) __('made-cms-package::media.browser.kinds.image', [], $locale), 'empty' => $t('empty')]];
        $texts = self::texts('picker.texts', $locale);
        $modes = ['image' => [
            'title' => $t('title'),
            'nothing_hint' => $texts['nothing_hint'] ?? '',
            'choose_first' => $texts['choose_first'] ?? '',
            'alt' => $texts['alt'] ?? '',
            'no_alt' => $texts['no_alt'] ?? '',
        ]];

        foreach (['document', 'video', 'file'] as $mode) {
            $own = self::texts('picker.modes.'.$mode, $locale);
            $browser['config']['modes'][$mode] = ['kind' => $own['kind'] ?? '', 'empty' => $own['empty'] ?? ''];
            $modes[$mode] = array_diff_key($own, ['kind' => true, 'empty' => true]);
        }

        return [
            't' => $t,
            'browser' => $browser,
            'config' => ['texts' => $texts, 'modes' => $modes],
        ];
    }

    /**
     * The media library: the list of every kind on a screen of its own, with the upload field of several files that
     * hands the photos of an upload to the crop step, where the editor also manages the folders and moves the files
     * they chose. The script gets the filter it opens with, the file it opens
     * with chosen and its first page and folders in the shape of their endpoints, so it shows them without loading;
     * it keeps its address in step with what it lists.
     *
     * @param  int|null  $chosen  the file the library opens with chosen; null for none
     * @return Browser
     */
    public static function library(MediaLibrary $library, ?int $chosen = null): array
    {
        $browser = self::make($library->folder, null, UploadField::make($library->folder, crop: true));
        $locale = Locale::launcher();
        $browser['library'] = true;
        $base = request()->getBaseUrl();
        $browser['config']['library'] = true;
        $browser['config']['urls'] = [
            ...$browser['config']['urls'],
            'folders_store' => $base.route('made-cms-package.media.folders.store', [], false),
            'folder' => $base.route('made-cms-package.media.folders.update', ['folder' => ':folder'], false),
            'move_many' => $base.route('made-cms-package.media.move.many', [], false),
            'delete' => $base.route('made-cms-package.media.destroy', [], false),
        ];
        $browser['config']['folder_max'] = CheckMediaFolderName::MAX_LENGTH;
        $browser['config']['texts'] = [...$browser['config']['texts'], ...self::texts('library.texts', $locale)];
        $browser['config']['kinds'] = self::texts('kinds', $locale);
        $browser['config']['filter'] = [
            'kind' => $library->filter->kind?->value,
            'without_alt' => $library->filter->withoutAlt,
            'q' => $library->filter->search,
            'sort' => $library->filter->sort->value,
        ];
        $browser['config']['chosen'] = $chosen;
        $browser['config']['initial'] = [
            'files' => MediaResponse::list($library->list, $library->filter),
            'folders' => $library->folders->toArray(),
        ];

        return $browser;
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
