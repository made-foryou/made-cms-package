<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Http\Cms\StartPageSample;
use Made\CmsPackage\Support\Locale;

/**
 * The start page: a note of the day in the editor's words, then the same four blocks for every role.
 */
final class StartPageResponse
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     */
    public static function make(Editor $editor, array $screens): Response
    {
        $now = Carbon::now();

        return Shell::respond('made-cms-package-launcher::cms.start', $editor, $screens, null, [
            'today' => Str::ucfirst($now->settings(['locale' => Locale::launcher()])->isoFormat('dddd D MMMM')),
            'greeting' => self::greeting($now->hour),
            'blocks' => StartPageSample::for($editor->roles->all(), $screens, Shell::helpUrl()),
        ]);
    }

    /**
     * @return 'morning'|'afternoon'|'evening'
     */
    private static function greeting(int $hour): string
    {
        return match (true) {
            $hour >= 6 && $hour < 12 => 'morning',
            $hour >= 12 && $hour < 18 => 'afternoon',
            default => 'evening',
        };
    }
}
