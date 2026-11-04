<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\EditableStrip;
use Made\CmsPackage\Content\Data\EditableStrips;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Http\Middleware\ResolvePage;
use Made\CmsPackage\Support\Locale;

/**
 * The edit shell of a page: the site in the edit mode in a frame of the same origin, with a bar and a side panel.
 */
final class EditPageResponse
{
    /**
     * @param  list<Screen>  $screens  the screens of the editor, in menu order (ListScreens)
     */
    public static function make(Editor $editor, array $screens, Page $page, EditableStrips $strips): Response
    {
        $locale = Locale::launcher();
        $t = fn (string $key, array $replace = []): string => (string) __('made-cms-package::cms.edit.'.$key, $replace, $locale);
        $path = $page->route?->path;

        return Shell::respond('made-cms-package-launcher::cms.edit', $editor, $screens, Screen::Pages, [
            'page' => [
                'title' => $page->title,
                'status' => $page->status->value,
                'live' => $page->status === PageStatus::Published,
            ],
            // A relative address keeps the frame on the origin of the shell, whatever app.url says.
            'frameUrl' => $path === null ? null : request()->getBaseUrl().$path.'?'.ResolvePage::QUERY.'='.ResolvePage::EDIT,
            'editScript' => LauncherAssetController::url('edit.js'),
            'panelScript' => LauncherAssetController::url('panel.js'),
            'config' => [
                // The side panel talks JSON with the server, so the shell and the site in its frame stay loaded.
                'csrf_token' => csrf_token(),
                'urls' => [
                    'strip' => request()->getBaseUrl().route('made-cms-package.pages.strips.show', ['page' => $page, 'strip' => ':strip'], false),
                ],
                'strips' => array_map(static fn (EditableStrip $strip): array => [
                    'id' => $strip->id,
                    'type' => $strip->type,
                    'label' => $strip->label,
                    'visible' => $strip->visible,
                    'fields' => trans_choice('made-cms-package::cms.edit.fields', $strip->fields, [], $locale),
                ], $strips->strips),
                'texts' => [
                    'position' => $t('position'),
                    'unknown' => $t('unknown'),
                    'missing' => $t('missing'),
                    'hidden' => $t('hidden'),
                    'panel' => self::panelTexts($locale),
                ],
            ],
        ]);
    }

    /**
     * The texts the side panel fills in itself (counts, names of items, the state of saving); the fixed ones are in
     * the templates of the view.
     *
     * @return array<string, string>
     */
    private static function panelTexts(string $locale): array
    {
        $texts = __('made-cms-package::cms.edit.panel_texts', [], $locale);

        return is_array($texts) ? array_map(strval(...), $texts) : [];
    }
}
