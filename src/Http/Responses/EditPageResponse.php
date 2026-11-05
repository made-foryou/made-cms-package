<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\DraftChanges;
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
    public static function make(Editor $editor, array $screens, Page $page, EditableStrips $strips, DraftChanges $changes): Response
    {
        $locale = Locale::launcher();
        $t = fn (string $key, array $replace = []): string => (string) __('made-cms-package::cms.edit.'.$key, $replace, $locale);
        $path = $page->route?->path;
        $base = request()->getBaseUrl();
        $draft = DraftResponse::data($changes);

        return Shell::respond('made-cms-package-launcher::cms.edit', $editor, $screens, Screen::Pages, [
            'page' => [
                'title' => $page->title,
                'status' => $page->status->value,
                'live' => $page->status === PageStatus::Published,
            ],
            'draft' => $draft,
            // A relative address keeps the frame on the origin of the shell, whatever app.url says.
            'frameUrl' => $path === null ? null : $base.$path.'?'.ResolvePage::QUERY.'='.ResolvePage::EDIT,
            'previewUrl' => $path === null ? null : $base.$path.'?'.ResolvePage::QUERY.'='.ResolvePage::PREVIEW,
            'editScript' => LauncherAssetController::url('edit.js'),
            'panelScript' => LauncherAssetController::url('panel.js'),
            'barScript' => LauncherAssetController::url('bar.js'),
            'config' => [
                // The side panel and the bar talk JSON with the server, so the shell and the site in its frame stay loaded.
                'csrf_token' => csrf_token(),
                'urls' => [
                    'strip' => $base.route('made-cms-package.pages.strips.show', ['page' => $page, 'strip' => ':strip'], false),
                    'draft' => $base.route('made-cms-package.pages.draft.show', ['page' => $page], false),
                    'publish' => $base.route('made-cms-package.pages.publish', ['page' => $page], false),
                ],
                'draft' => $draft,
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
                    'bar' => [
                        'live' => (string) __('made-cms-package::pages.status.'.PageStatus::Published->value, [], $locale),
                        'draft' => (string) __('made-cms-package::pages.status.'.PageStatus::Draft->value, [], $locale),
                        ...self::texts('cms.edit.bar_texts', $locale),
                    ],
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
        return self::texts('cms.edit.panel_texts', $locale);
    }

    /**
     * @return array<string, string>
     */
    private static function texts(string $key, string $locale): array
    {
        $texts = __('made-cms-package::'.$key, [], $locale);

        return is_array($texts) ? array_map(strval(...), $texts) : [];
    }
}
