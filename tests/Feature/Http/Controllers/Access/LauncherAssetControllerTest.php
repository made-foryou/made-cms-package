<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;

it('serves the launcher assets with their content type', function (string $asset, string $contentType): void {
    $response = $this->get("cms/launcher/{$asset}");

    $response->assertOk()
        ->assertHeader('Content-Type', $contentType);

    expect($response->headers->getCacheControlDirective('max-age'))->toBe('31536000')
        ->and($response->headers->hasCacheControlDirective('immutable'))->toBeTrue()
        ->and($response->headers->hasCacheControlDirective('public'))->toBeTrue();
})->with([
    ['launcher.css', 'text/css; charset=utf-8'],
    ['launcher.js', 'text/javascript; charset=utf-8'],
    ['nunito-700.woff2', 'font/woff2'],
    ['instrument-sans.woff2', 'font/woff2'],
]);

it('serves the assets without a session or cookies', function (): void {
    $response = $this->get('cms/launcher/launcher.js');

    expect($response->headers->getCookies())->toBeEmpty();
});

it('only serves known launcher assets', function (string $uri): void {
    $this->get($uri)->assertNotFound();
})->with([
    'unknown file' => 'cms/launcher/secret.php',
    'font licence' => 'cms/launcher/OFL-Nunito.txt',
    'launcher view' => 'cms/launcher/launcher.blade.php',
    'path traversal' => 'cms/launcher/..%2F..%2Fcomposer.json',
    'nested path' => 'cms/launcher/fonts/nunito-700.woff2',
]);

it('versions each asset url with a hash of its contents', function (string $asset, string $file): void {
    $hash = substr((string) hash_file('xxh128', __DIR__."/../../../../../resources/launcher/{$file}"), 0, 12);

    $url = LauncherAssetController::url($asset);

    expect($url)->toBe("/cms/launcher/{$asset}?v={$hash}");

    $this->get($url)->assertOk();
})->with([
    ['launcher.css', 'launcher.css'],
    ['launcher.js', 'launcher.js'],
    ['nunito-700.woff2', 'fonts/nunito-700.woff2'],
    ['instrument-sans.woff2', 'fonts/instrument-sans.woff2'],
]);

it('keeps the base path of an app served from a subdirectory', function (): void {
    $request = Request::create('http://example.nl/site/', 'GET', server: [
        'SCRIPT_NAME' => '/site/index.php',
        'SCRIPT_FILENAME' => '/var/www/site/index.php',
    ]);

    $this->app->instance('request', $request);
    app('url')->setRequest($request);

    expect(LauncherAssetController::url('launcher.css'))->toStartWith('/site/cms/launcher/launcher.css?v=');
});
