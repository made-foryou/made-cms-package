<?php

declare(strict_types=1);

namespace Made\CmsPackage\Http\Controllers\Content;

use Illuminate\Http\JsonResponse;
use Made\CmsPackage\Content\Actions\GenerateStripTypes;
use Made\CmsPackage\Http\Requests\Content\GenerateStripTypesRequest;
use Made\CmsPackage\Http\Responses\GeneratedStripTypesResponse;

final class GenerateStripTypesController
{
    public function __invoke(GenerateStripTypesRequest $request): JsonResponse
    {
        return GeneratedStripTypesResponse::of(GenerateStripTypes::run($request->toData()));
    }
}
