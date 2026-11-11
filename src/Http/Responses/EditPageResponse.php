<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\Response;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\DraftChanges;
use Made\CmsPackage\Content\Data\EditableStrips;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Http\Cms\Shell;
use Made\CmsPackage\Http\Cms\UploadField;
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
     * @param  StripTypes  $types  the catalogue an editor adds strips from
     * @param  bool  $headingLevels  whether the editor chooses the level of a heading (Visitor::choosesHeadingLevels)
     * @param  MediaFolder|null  $folder  the folder the editor chose last in the picker (MediaFolderMemory); null for "Alle bestanden"
     */
    public static function make(Editor $editor, array $screens, Page $page, EditableStrips $strips, DraftChanges $changes, StripTypes $types, bool $headingLevels, ?MediaFolder $folder = null): Response
    {
        $locale = Locale::launcher();
        $t = fn (string $key, array $replace = []): string => (string) __('made-cms-package::cms.edit.'.$key, $replace, $locale);
        $path = $page->route?->path;
        $base = request()->getBaseUrl();
        $draft = DraftResponse::data($changes);
        // The media element uploads a photo into the folder the editor chose last in the picker.
        $upload = UploadField::image($folder);

        return Shell::respond('made-cms-package-launcher::cms.edit', $editor, $screens, Screen::Pages, [
            'page' => [
                'title' => $page->title,
                'status' => $page->status->value,
                'live' => $page->status === PageStatus::Published,
            ],
            'draft' => $draft,
            'counts' => PageStripsResponse::counts($strips),
            // A relative address keeps the frame on the origin of the shell, whatever app.url says.
            'frameUrl' => $path === null ? null : $base.$path.'?'.ResolvePage::QUERY.'='.ResolvePage::EDIT,
            'previewUrl' => $path === null ? null : $base.$path.'?'.ResolvePage::QUERY.'='.ResolvePage::PREVIEW,
            'editScript' => LauncherAssetController::url('edit.js'),
            'panelScript' => LauncherAssetController::url('panel.js'),
            'barScript' => LauncherAssetController::url('bar.js'),
            'structureScript' => LauncherAssetController::url('structure.js'),
            'upload' => $upload,
            'uploadScript' => $upload['script'],
            // The tab Koppen is only UI, for whoever chooses the level of a heading.
            'headingLevels' => $headingLevels,
            'headingsScript' => $headingLevels ? LauncherAssetController::url('headings.js') : null,
            'config' => [
                // The side panel and the bar talk JSON with the server, so the shell and the site in its frame stay loaded.
                'csrf_token' => csrf_token(),
                'urls' => [
                    'strips' => $base.route('made-cms-package.pages.strips.store', ['page' => $page], false),
                    'strip' => $base.route('made-cms-package.pages.strips.show', ['page' => $page, 'strip' => ':strip'], false),
                    'draft' => $base.route('made-cms-package.pages.draft.show', ['page' => $page], false),
                    'publish' => $base.route('made-cms-package.pages.publish', ['page' => $page], false),
                ],
                'draft' => $draft,
                // Every strip of the working copy in its stored order: its place is the position the endpoints take.
                'strips' => PageStripsResponse::strips($strips),
                'counts' => PageStripsResponse::counts($strips),
                'types' => array_map(static fn (StripType $type): array => [
                    'key' => $type->key(),
                    'label' => $type->label(),
                    'description' => $type->description(),
                    'fields' => trans_choice('made-cms-package::cms.edit.fields', count($type->fields()), [], $locale),
                ], $types->all()),
                'page' => ['title' => $page->title],
                // The folder the picker opens in and the uploads of the panel go into, until the editor opens another.
                'folder' => $folder === null ? null : ['id' => $folder->id, 'name' => $folder->name],
                // Only UI: the strip Actions keep the level of a heading for an editor who does not choose it.
                'heading_levels' => $headingLevels,
                'texts' => [
                    'position' => $t('position'),
                    'unknown' => $t('unknown'),
                    'missing' => $t('missing'),
                    'hidden' => $t('hidden'),
                    'panel' => self::panelTexts($locale),
                    'structure' => self::texts('cms.edit.structure_texts', $locale),
                    'bar' => [
                        'live' => (string) __('made-cms-package::pages.status.'.PageStatus::Published->value, [], $locale),
                        'draft' => (string) __('made-cms-package::pages.status.'.PageStatus::Draft->value, [], $locale),
                        ...self::texts('cms.edit.bar_texts', $locale),
                    ],
                    ...($headingLevels ? ['headings' => self::headingTexts($locale)] : []),
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
     * The texts the overview of the headings fills in (sources, signals, counts), with the name of every level as its
     * badge shows it: "H1" to "H6", and "span" for a heading at "Geen kop".
     *
     * @return array<string, mixed>
     */
    private static function headingTexts(string $locale): array
    {
        $texts = self::texts('cms.edit.headings_texts', $locale);
        $levels = [];

        foreach (Heading::TAGS as $tag) {
            $levels[$tag] = $tag === 'span'
                ? ($texts['span'] ?? $tag)
                : (string) __('made-cms-package::strips.heading.tags.'.$tag, [], $locale);
        }

        return [...$texts, 'levels' => $levels];
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
