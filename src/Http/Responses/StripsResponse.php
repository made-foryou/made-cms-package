<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Responses;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Data\StripDescription;

/**
 * The strips of a page as the edit mode outlines them: id, type and the label of the type. Data only.
 */
final class StripsResponse
{
    /**
     * @param  list<StripDescription>  $strips
     */
    public static function make(array $strips): JsonResponse
    {
        return new JsonResponse([
            'strips' => array_map(fn (StripDescription $strip): array => [
                'id' => $strip->id,
                'type' => $strip->type,
                'label' => $strip->label,
            ], $strips),
        ]);
    }
}
