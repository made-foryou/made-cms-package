<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\UpdateAltText;
use Made\CmsPackage\Content\Data\MediaLibrary;
use Made\CmsPackage\Http\Cms\CropStep;
use Made\CmsPackage\Http\Cms\MediaBrowser;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Http\Cms\UploadField;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;

/**
 * The media library (design 13.1): the list of the picker on a screen of its own, which uploads into the folder it
 * is in and hands the photos of an upload to the crop step. Its first page comes along, so it shows without loading.
 */
final class MediaLibraryResponse
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     * @param  int|null  $chosen  the file the library opens with chosen; null for none
     */
    public static function make(Editor $editor, array $screens, MediaLibrary $library, ?int $chosen): Response
    {
        $browser = MediaBrowser::library($library, $chosen);

        return Shell::respond('made-cms-package-launcher::cms.media.index', $editor, $screens, Screen::Media, [
            'screen' => Screen::Media,
            'browser' => $browser,
            'library' => self::config($browser['upload']['limits'] ?? ''),
            'libraryScript' => LauncherAssetController::url('library.js'),
            // The field of a new version in the side column, pointed at the chosen file by library.js.
            'replace' => UploadField::replace(),
            // Moving a file into another folder is managing the library, which this screen is.
            'crop' => CropStep::make(moves: true),
        ]);
    }

    /**
     * What library.js needs, as JSON on the library itself: where it reads a file with its places, stores an alt text,
     * replaces a file, puts its former version back, saves it and deletes files, the names of the kinds, and the texts
     * it fills in.
     *
     * @return array{urls: array{show: string, alt: string, replace: string, restore: string, download: string, delete: string}, csrf_token: string, kinds: array<string, string>, limits: string, alt_max: int, texts: array<string, string>}
     */
    private static function config(string $limits): array
    {
        $locale = Locale::launcher();
        $base = request()->getBaseUrl();
        $kinds = __('made-cms-package::media.kinds', [], $locale);
        $texts = __('made-cms-package::media.library.texts', [], $locale);

        return [
            'urls' => [
                'show' => $base.route('made-cms-package.media.show', ['media' => ':media'], false),
                'alt' => $base.route('made-cms-package.media.alt.update', ['media' => ':media'], false),
                'replace' => $base.route('made-cms-package.media.replace', ['media' => ':media'], false),
                'restore' => $base.route('made-cms-package.media.restore', ['media' => ':media'], false),
                'download' => $base.route('made-cms-package.media.download', ['media' => ':media'], false),
                'delete' => $base.route('made-cms-package.media.destroy', [], false),
            ],
            'csrf_token' => (string) csrf_token(),
            'kinds' => is_array($kinds) ? array_map(strval(...), $kinds) : [],
            'limits' => rtrim($limits, '.'),
            'alt_max' => UpdateAltText::MAX_LENGTH,
            'texts' => [
                ...(is_array($texts) ? array_map(strval(...), $texts) : []),
                'drop_title' => (string) __('made-cms-package::media.library.drop_title', [], $locale),
                'drop_title_folder' => (string) __('made-cms-package::media.library.drop_title_folder', [], $locale),
                'usage' => (string) __('made-cms-package::media.library.usage', [], $locale),
                'alt_long' => (string) __('made-cms-package::media.errors.alt_long', ['max' => UpdateAltText::MAX_LENGTH], $locale),
            ],
        ];
    }
}
