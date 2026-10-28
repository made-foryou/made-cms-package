<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\DescribeStrips;
use Made\CmsPackage\Http\Requests\Content\ShowStripsRequest;
use Made\CmsPackage\Http\Responses\StripsResponse;

/**
 * The edit mode asks which strips the ids on the page are, to outline them with the label of their type.
 */
final class StripsController
{
    public function __invoke(ShowStripsRequest $request): JsonResponse
    {
        return StripsResponse::make(DescribeStrips::run($request->toData()));
    }
}
