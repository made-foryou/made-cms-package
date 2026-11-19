<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Cms;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Made\CmsPackage\Content\Actions\DescribeUploadLimits;
use Made\CmsPackage\Content\Data\UploadLimits;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;
use Made\CmsPackage\Support\Locale;

/**
 * The upload field of the CMS: one field for the media library, the picker and the edit shell. A host includes the
 * partial `cms.partials.upload` with what make(), image(), images() or file() gives as `$upload` and loads its script.
 *
 * @phpstan-type Field array{
 *     t: Closure(string): string,
 *     rules: string,
 *     limits: string,
 *     accept: string,
 *     one: bool,
 *     replace: bool,
 *     script: string,
 *     config: array{
 *         urls: array{upload: string, alt: string},
 *         csrf_token: string,
 *         folder: array{id: int|null, label: string},
 *         limits: array{max_bytes: int},
 *         one: bool,
 *         kinds: list<string>|null,
 *         replace: bool,
 *         accepts: array<string, string>,
 *         crop: bool,
 *         texts: array<string, string>,
 *     },
 * }
 */
final class UploadField
{
    /**
     * What the partial and the script of the field need. The script gets its config as JSON on the field itself
     * (`data-config`), so a page can hold more than one field: where a file and its alt text go, the folder the
     * files go into, the largest file (the server checks it again) and the texts it fills in.
     *
     * @param  MediaFolder|null  $folder  the folder the files go into; null for none ("Alle bestanden")
     * @param  bool  $crop  whether the photos of a batch go to the crop step on the page (made-cms:crop), which then
     *                      asks their alt text instead of the field
     * @return Field
     */
    public static function make(?MediaFolder $folder = null, bool $crop = false): array
    {
        return self::field($folder, one: false, crop: $crop);
    }

    /**
     * The field of an image in a strip, the media element of the side panel: it takes one photo at a time and no
     * document or video, and says so in the singular. The library still checks every file it gets.
     *
     * @param  MediaFolder|null  $folder  the folder the photo goes into; null for none ("Alle bestanden")
     * @param  bool  $crop  whether the photo goes to the crop step on the page (made-cms:crop) once it is in
     * @return Field
     */
    public static function image(?MediaFolder $folder = null, bool $crop = false): array
    {
        return self::field($folder, one: true, crop: $crop, kinds: [MediaKind::Image], own: 'single');
    }

    /**
     * The field of a list of images in a strip, in the picker opened from an item of that list: it takes several photos
     * at once and no document or video. The library still checks every file it gets.
     *
     * @param  MediaFolder|null  $folder  the folder the photos go into; null for none ("Alle bestanden")
     * @param  bool  $crop  whether the photos of a batch go to the crop step on the page (made-cms:crop) once they are in
     * @return Field
     */
    public static function images(?MediaFolder $folder = null, bool $crop = false): array
    {
        return self::field($folder, one: false, crop: $crop, kinds: [MediaKind::Image], own: 'images');
    }

    /**
     * The field of a file field in a strip (a document, a video or both), the media element of the side panel and the
     * picker in its stand for that kind: it takes one file at a time of those kinds and says so in their words
     * (upload.document, upload.video, upload.file for both). It asks the description of the file, never a crop. Null
     * when the library takes none of those kinds (media.allowed_mimes), so the editor only chooses from Media.
     *
     * @param  MediaFolder|null  $folder  the folder the file goes into; null for none ("Alle bestanden")
     * @return Field|null
     */
    public static function file(?MediaFolder $folder, MediaKind ...$kinds): ?array
    {
        $taken = array_values(array_filter(
            DescribeUploadLimits::run()->kinds,
            static fn (MediaKind $kind): bool => $kind !== MediaKind::Image && in_array($kind, $kinds, true),
        ));
        usort($taken, static fn (MediaKind $a, MediaKind $b): int => array_search($a, MediaKind::cases(), true) <=> array_search($b, MediaKind::cases(), true));

        return $taken === [] ? null : self::field($folder, one: true, crop: false, kinds: $taken, own: count($taken) === 1 ? $taken[0]->value : 'file');
    }

    /**
     * The field that takes the new version of a file of the library, in its side column: one file at a time, sent to
     * the file it replaces, which the host names with made-cms:upload-target {media: {id, kind, name}}. The field then
     * takes only that kind and says so; a photo goes to the crop step once it is in. The alt text and the folder of the
     * file stay, so the field asks neither. ReplaceMedia checks the file again, its kind too.
     *
     * @return Field
     */
    public static function replace(): array
    {
        return self::field(null, one: false, crop: true, replace: true, own: 'replace');
    }

    /**
     * @param  bool  $one  whether it takes one file at a time
     * @param  list<MediaKind>|null  $kinds  the kinds it takes; null for every kind the library takes
     * @param  string|null  $own  the texts that differ from the general ones: the field for one photo speaks in the
     *                            singular (upload.single), the field of a list of images of photos (upload.images), the
     *                            field of a file field of its kind (upload.document, upload.video, upload.file), the field
     *                            of a new version in its own words (upload.replace)
     * @return Field
     */
    private static function field(?MediaFolder $folder, bool $one, bool $crop, ?array $kinds = null, bool $replace = false, ?string $own = null): array
    {
        $locale = Locale::launcher();
        $t = static function (string $key, array $replace = []) use ($locale, $own): string {
            $special = 'made-cms-package::media.upload.'.$own.'.'.$key;

            return (string) __($own !== null && Lang::has($special, $locale) ? $special : 'made-cms-package::media.upload.'.$key, $replace, $locale);
        };
        $limits = DescribeUploadLimits::run();
        $mimes = $kinds === null
            ? $limits->mimes()
            : array_values(array_filter($limits->mimes(), static fn (string $mime): bool => in_array(MediaKind::fromMime($mime), $kinds, true)));
        $shown = $kinds ?? $limits->kinds;
        $base = request()->getBaseUrl();
        $label = $folder === null ? $t('all_files') : $t('folder', ['name' => $folder->name]);
        // The line of rules with the folder left open, so the script can name another folder in it (upload-folder).
        $kindList = Str::ucfirst(self::list(
            array_map(static fn (MediaKind $kind): string => $t('kinds.'.$kind->value), $shown),
            (string) __('made-cms-package::media.and', [], $locale),
        ));
        $rules = $t('rules', ['kinds' => $kindList, 'max' => $limits->max]);

        return [
            't' => static fn (string $key): string => $t($key),
            // The field of a new version names the types of the kind it replaces once it knows that kind.
            'rules' => $replace ? '' : str_replace(':folder', $label, $rules),
            // What the field takes without the folder, for a host that tells it beside a drop zone of its own.
            'limits' => $t('limits', ['kinds' => $kindList, 'max' => $limits->max]),
            'accept' => implode(',', $mimes),
            'one' => $one,
            'replace' => $replace,
            'script' => LauncherAssetController::url('upload.js'),
            'config' => [
                'urls' => [
                    'upload' => $base.($replace
                        ? route('made-cms-package.media.replace', ['media' => ':media'], false)
                        : route('made-cms-package.media.store', [], false)),
                    'alt' => $base.route('made-cms-package.media.alt.update', ['media' => ':media'], false),
                ],
                'csrf_token' => (string) csrf_token(),
                'folder' => ['id' => $folder?->id, 'label' => $label],
                // Only to spare an editor an upload the server refuses anyway; CheckUploadedMedia decides.
                'limits' => ['max_bytes' => $limits->maxBytes],
                'one' => $one,
                // The kinds the field takes, so it refuses another kind before it travels (null for every kind the
                // library takes); the server reads the kind from the contents again.
                'kinds' => $kinds === null ? null : array_map(static fn (MediaKind $kind): string => $kind->value, $kinds),
                'replace' => $replace,
                // What the file chooser offers for each kind a new version can be of.
                'accepts' => $replace ? self::accepts($limits) : [],
                'crop' => $crop,
                'texts' => [
                    ...self::texts('upload.texts', $locale),
                    ...($replace ? self::replaceTexts($limits, $locale) : ($own !== null ? self::texts('upload.'.$own.'.texts', $locale) : [])),
                    'processing_image' => (string) __('made-cms-package::media.upload.texts.processing_image', [
                        'format' => config()->string('made-cms-package.media.image.format'),
                        'width' => config()->integer('made-cms-package.media.image.max_width'),
                    ], $locale),
                    'too_large' => (string) __('made-cms-package::media.errors.too_large', ['max' => $limits->max], $locale),
                    'too_large_video' => (string) __('made-cms-package::media.errors.too_large_video', ['max' => $limits->max], $locale),
                    'server_limit' => (string) __('made-cms-package::media.errors.server_limit', ['max' => self::serverLimit()], $locale),
                    'rules' => $rules,
                    'folder' => $t('folder'),
                    'all_files' => $t('all_files'),
                    'kb' => (string) __('made-cms-package::media.details.kb', [], $locale),
                    'mb' => (string) __('made-cms-package::media.details.mb', [], $locale),
                    'decimal' => (string) __('made-cms-package::media.details.decimal', [], $locale),
                ],
            ],
        ];
    }

    /**
     * The types the library takes, by the kind of file they are: "image" => "image/jpeg,image/png".
     *
     * @return array<string, string>
     */
    private static function accepts(UploadLimits $limits): array
    {
        $accepts = [];

        foreach ($limits->mimes() as $mime) {
            $accepts[MediaKind::fromMime($mime)->value][] = $mime;
        }

        return array_map(static fn (array $mimes): string => implode(',', $mimes), $accepts);
    }

    /**
     * The words of the field of a new version, and for each kind the types it takes ("jpg, png of svg · tot 25 MB") and
     * the refusal of a file of another kind, the one ReplaceMedia gives.
     *
     * @return array<string, string>
     */
    private static function replaceTexts(UploadLimits $limits, string $locale): array
    {
        $texts = self::texts('upload.replace.texts', $locale);
        $types = [];

        foreach ($limits->extensions as $mime => $extension) {
            $types[MediaKind::fromMime($mime)->value][] = $extension;
        }

        foreach ($types as $kind => $extensions) {
            $texts['rules_'.$kind] = (string) __('made-cms-package::media.upload.replace.rules', [
                'types' => self::list($extensions, (string) __('made-cms-package::media.upload.or', [], $locale)),
                'max' => $limits->max,
            ], $locale);
            $texts['kind_'.$kind] = (string) __('made-cms-package::media.errors.replace_kind.'.$kind, [], $locale);
        }

        return $texts;
    }

    /**
     * The texts under a key of media.upload, as plain strings.
     *
     * @return array<string, string>
     */
    private static function texts(string $key, string $locale): array
    {
        $texts = __('made-cms-package::media.'.$key, [], $locale);

        return is_array($texts) ? array_map(strval(...), $texts) : [];
    }

    /**
     * The largest upload php itself takes (upload_max_filesize and post_max_size), as an editor reads it: "2 MB".
     * Above it a file never reaches the library, whatever media.max_upload_kb says.
     */
    public static function serverLimit(): string
    {
        $kilobytes = intdiv((int) UploadedFile::getMaxFilesize(), 1024);

        return $kilobytes % 1024 === 0 ? intdiv($kilobytes, 1024).' MB' : $kilobytes.' kB';
    }

    /**
     * Names as a sentence lists them: "foto’s, documenten en video’s".
     *
     * @param  list<string>  $names
     */
    private static function list(array $names, string $and): string
    {
        $last = array_pop($names);

        return $names === [] ? (string) $last : implode(', ', $names).' '.$and.' '.$last;
    }
}
