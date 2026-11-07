<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Data\GeneratedStripType;
use Made\CmsPackage\Content\Data\GeneratedStripTypes;
use Made\CmsPackage\Content\Data\SkippedStripType;
use Made\CmsPackage\Support\Locale;

/**
 * What the scan wrote into the site and what it skipped, with the texts made here, so the scan panel never builds a
 * plural itself. A file in the site is named from the root of the site.
 */
final class GeneratedStripTypesResponse
{
    public static function of(GeneratedStripTypes $result): JsonResponse
    {
        $locale = Locale::launcher();
        $summary = trans_choice('made-cms-package::scan.generate.written', count($result->written), [], $locale);

        if ($result->skipped !== []) {
            $summary .= ', '.trans_choice('made-cms-package::scan.generate.skipped_count', count($result->skipped), [], $locale);
        }

        $response = new JsonResponse([
            'written' => array_map(static fn (GeneratedStripType $type): array => [
                'key' => $type->key,
                'class' => $type->class,
                'path' => self::fromRoot($type->path),
                'left_out' => $type->leftOut,
            ], $result->written),
            'skipped' => array_map(static fn (SkippedStripType $type): array => [
                'key' => $type->key,
                'class' => $type->class,
                'reason' => $type->reason->value,
                'label' => $type->reason->label($locale),
                'detail' => $type->detail,
            ], $result->skipped),
            'summary' => $summary,
        ]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private static function fromRoot(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $path = str_replace('\\', '/', $path);

        return str_starts_with($path, $root) ? substr($path, strlen($root)) : $path;
    }
}
