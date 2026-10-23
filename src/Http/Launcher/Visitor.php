<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Launcher;

use Illuminate\Http\Request;
use Made\CmsPackage\Access\Actions\DescribeEditor;
use Made\CmsPackage\Access\Data\LauncherVisitor;
use Made\CmsPackage\Access\Models\Editor;
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
        );
    }

    public static function editor(Request $request): ?Editor
    {
        $editor = $request->user(config()->string('made-cms-package.auth.guard'));

        return $editor instanceof Editor ? $editor : null;
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
