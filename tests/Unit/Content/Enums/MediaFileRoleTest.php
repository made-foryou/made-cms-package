<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\MediaFileRole;

it('writes every file a visitor opens public with a cache header for a year, and the original private', function (MediaFileRole $role, array $options): void {
    expect($role->writeOptions())->toBe($options);
})->with([
    'the photo' => [MediaFileRole::Photo, ['visibility' => 'public', 'directory_visibility' => 'public', 'CacheControl' => MediaFileRole::CACHE_CONTROL]],
    'a narrower size' => [MediaFileRole::Variant, ['visibility' => 'public', 'directory_visibility' => 'public', 'CacheControl' => MediaFileRole::CACHE_CONTROL]],
    'the thumbnail' => [MediaFileRole::Thumbnail, ['visibility' => 'public', 'directory_visibility' => 'public', 'CacheControl' => MediaFileRole::CACHE_CONTROL]],
    'a file as it came in' => [MediaFileRole::AsUploaded, ['visibility' => 'public', 'directory_visibility' => 'public', 'CacheControl' => MediaFileRole::CACHE_CONTROL]],
    'the original' => [MediaFileRole::Original, ['visibility' => 'private', 'directory_visibility' => 'public']],
]);
