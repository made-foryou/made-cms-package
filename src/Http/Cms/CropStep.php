<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Closure;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;

/**
 * The crop step of a photo (design 13.6, 14.6): the partial `cms.partials.crop` with what make() gives as `$crop`, and
 * its script. A host includes it once; the media element and the upload field open it with made-cms:crop.
 *
 * @phpstan-type Step array{
 *     t: Closure(string): string,
 *     ratios: list<string>,
 *     moves: bool,
 *     script: string,
 *     config: array{
 *         urls: array{details: string, original: string, crop: string, name: string, alt: string, move: string|null, folders: string|null},
 *         csrf_token: string,
 *         moves: bool,
 *         max_width: int,
 *         format: string,
 *         texts: array<string, string>,
 *     },
 * }
 */
final class CropStep
{
    /** The ratios of the crop, in the order of the design; "free" keeps any size, "original" the one of the photo. */
    public const array RATIOS = ['free', '16:9', '4:3', '3:2', '1:1', 'original'];

    /**
     * What the partial and the script of the step need. The script gets its config as JSON on the dialog itself
     * (`data-config`): the addresses of a file (":media" for its id), the size a photo is scaled down to and the texts
     * it fills in. Moving a file into a folder is managing the library: only with $moves the step shows "Map".
     *
     * @param  bool  $moves  whether the editor opens the media library (Screen::Media)
     * @return Step
     */
    public static function make(bool $moves): array
    {
        $locale = Locale::launcher();
        $base = request()->getBaseUrl();
        $url = static fn (string $name): string => $base.route('made-cms-package.media.'.$name, ['media' => ':media'], false);
        $maxWidth = config()->integer('made-cms-package.media.image.max_width');
        $format = config()->string('made-cms-package.media.image.format');
        $texts = __('made-cms-package::media.crop.texts', [], $locale);

        return [
            't' => static fn (string $key): string => (string) __('made-cms-package::media.crop.'.$key, ['width' => $maxWidth], $locale),
            'ratios' => self::RATIOS,
            'moves' => $moves,
            'script' => LauncherAssetController::url('crop.js'),
            'config' => [
                'urls' => [
                    'details' => $url('crop.show'),
                    'original' => $url('original'),
                    'crop' => $url('crop.store'),
                    'name' => $url('name.update'),
                    'alt' => $url('alt.update'),
                    'move' => $moves ? $url('move') : null,
                    'folders' => $moves ? $base.route('made-cms-package.media.folders', [], false) : null,
                ],
                'csrf_token' => (string) csrf_token(),
                'moves' => $moves,
                'max_width' => $maxWidth,
                // The format as an editor reads it: "WebP", "JPG".
                'format' => $format === 'webp' ? 'WebP' : strtoupper($format),
                'texts' => [
                    ...(is_array($texts) ? array_map(strval(...), $texts) : []),
                    'all_files' => (string) __('made-cms-package::media.crop.all_files', [], $locale),
                    'kb' => (string) __('made-cms-package::media.details.kb', [], $locale),
                    'mb' => (string) __('made-cms-package::media.details.mb', [], $locale),
                    'decimal' => (string) __('made-cms-package::media.details.decimal', [], $locale),
                ],
            ],
        ];
    }
}
