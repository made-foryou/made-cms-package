<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Http\Cms\Shell;

/**
 * A screen that is not built yet: its place in the menu and a note that it is coming.
 */
final class UpcomingScreenResponse
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     */
    public static function make(Editor $editor, array $screens, Screen $screen): Response
    {
        return Shell::respond('made-cms-package-launcher::cms.upcoming', $editor, $screens, $screen, [
            'screen' => $screen,
        ]);
    }
}
