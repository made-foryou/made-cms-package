<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Launcher;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Access\Actions\CheckScreenAccess;
use Made\CmsPackage\Access\Actions\DescribeEditor;
use Made\CmsPackage\Access\Data\LauncherVisitor;
use Made\CmsPackage\Access\Data\ScreenVisit;
use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\EditMode;
use Made\CmsPackage\Http\Cookies\EditorDevice;

/**
 * Translates a request into the visitor the Access context decides on, and an editor into what the launcher shows of them.
 */
final class Visitor
{
    public static function of(Request $request): LauncherVisitor
    {
        return new LauncherVisitor(
            ip: $request->ip(),
            remembered: EditorDevice::remembered($request),
            editor: self::editor($request),
            editing: app(EditMode::class)->active,
        );
    }

    public static function editor(Request $request): ?Editor
    {
        $editor = $request->user(config()->string('made-cms-package.auth.guard'));

        return $editor instanceof Editor ? $editor : null;
    }

    /**
     * Whether the logged-in editor chooses the level of a heading in the edit shell: whoever opens Meta-overzicht
     * (Screen::MetaTags). The others edit the text of a heading only; the strip Actions keep its level for them.
     */
    public static function choosesHeadingLevels(Request $request): bool
    {
        return CheckScreenAccess::run(new ScreenVisit(editor: self::editor($request), screen: Screen::MetaTags));
    }

    /**
     * The logged-in editor, or a message that the editor has to log in again.
     *
     * @throws ValidationException
     */
    public static function requireEditor(Request $request): Editor
    {
        return self::editor($request) ?? throw ValidationException::withMessages([
            'email' => __('made-cms-package::launcher.errors.expired'),
        ]);
    }

    /**
     * @return array{first_name: string|null, initial: string}|null
     */
    public static function profile(?Editor $editor): ?array
    {
        if ($editor === null) {
            return null;
        }

        $profile = DescribeEditor::run($editor);

        return ['first_name' => $profile->firstName, 'initial' => $profile->initial];
    }
}
