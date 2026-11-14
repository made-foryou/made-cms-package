<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\MediaLibrary;
use Made\CmsPackage\Http\Cms\CropStep;
use Made\CmsPackage\Http\Cms\MediaBrowser;
use Made\CmsPackage\Http\Cms\Shell;

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
        return Shell::respond('made-cms-package-launcher::cms.media.index', $editor, $screens, Screen::Media, [
            'screen' => Screen::Media,
            'browser' => MediaBrowser::library($library, $chosen),
            // Moving a file into another folder is managing the library, which this screen is.
            'crop' => CropStep::make(moves: true),
        ]);
    }
}
