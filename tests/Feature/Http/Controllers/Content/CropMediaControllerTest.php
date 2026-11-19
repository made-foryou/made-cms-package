<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A crop as the crop step sends it.
 *
 * @param  array<string, mixed>  $crop
 * @return array<string, mixed>
 */
function cropBody(array $crop = [], array $focal = ['x' => 0.25, 'y' => 0.75], bool $scaleDown = true): array
{
    return [
        'crop' => [...['x' => 0, 'y' => 0, 'width' => 400, 'height' => 225, 'rotation' => 0, 'flip' => false], ...$crop],
        'focal' => $focal,
        'scale_down' => $scaleDown,
    ];
}

function croppablePhoto(): Media
{
    return UploadMedia::run(new UploadedMedia(quadrants(800, 600), 'teamfoto.jpg'));
}

it('cuts the photo from its original and answers with the file as it is now', function (): void {
    $media = croppablePhoto();

    $response = $this->postJson('made-cms/media/'.$media->id.'/crop', cropBody(['x' => 40, 'y' => 30, 'rotation' => 90, 'flip' => true, 'width' => 225, 'height' => 400]))
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    $media->refresh();

    expect([$media->width, $media->height])->toBe([225, 400])
        ->and($media->crop)->toBeStoredAs(['x' => 40, 'y' => 30, 'width' => 225, 'height' => 400, 'rotation' => 90, 'flip' => true, 'scale_down' => true])
        ->and($response->json('file.id'))->toBe($media->id)
        ->and($response->json('file.meta'))->toStartWith('225 × 400 · ')
        ->and($response->json('file.url'))->toBe(Storage::disk('public')->url($media->path))
        ->and($response->json('file.image.focal'))->toBe(['x' => 0.25, 'y' => 0.75])
        ->and((string) $response->getContent())->not->toContain('original-');
});

it('refuses an area outside the photo', function (): void {
    $media = croppablePhoto();

    $this->postJson('made-cms/media/'.$media->id.'/crop', cropBody(['x' => 500]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.crop.0', 'De uitsnede valt buiten de foto. Herlaad de pagina en probeer het opnieuw.');

    expect($media->refresh()->crop)->toBeNull();
});

it('refuses a crop that is not whole', function (array $body, string $key): void {
    $media = croppablePhoto();

    $errors = $this->postJson('made-cms/media/'.$media->id.'/crop', $body)
        ->assertUnprocessable()
        ->json('errors');

    expect($errors[$key][0] ?? null)->toBe('Deze uitsnede kan zo niet opgeslagen worden. Herlaad de pagina en probeer het opnieuw.');
})->with([
    'without crop' => [['focal' => ['x' => 0.5, 'y' => 0.5], 'scale_down' => true], 'crop'],
    'a negative x' => [cropBody(['x' => -1]), 'crop.x'],
    'a width as text' => [cropBody(['width' => 'breed']), 'crop.width'],
    'no width' => [cropBody(['width' => 0]), 'crop.width'],
    'a turn at an angle' => [cropBody(['rotation' => 45]), 'crop.rotation'],
    'a flip as text' => [cropBody(['flip' => 'ja']), 'crop.flip'],
    'a focal point outside' => [cropBody(focal: ['x' => 1.5, 'y' => 0.5]), 'focal.x'],
    'without focal point' => [[...cropBody(), 'focal' => null], 'focal'],
    'without scale down' => [[...cropBody(), 'scale_down' => null], 'scale_down'],
]);

it('refuses a file that cannot be cropped, and one that is not in the library', function (): void {
    $this->postJson('made-cms/media/'.Media::factory()->document()->create()->id.'/crop', cropBody())
        ->assertUnprocessable()
        ->assertJsonPath('errors.crop.0', 'Dit bestand kan niet bijgesneden worden.');

    $this->postJson('made-cms/media/'.missingId().'/crop', cropBody())->assertNotFound();
});

it('opens for every editor who opens the pages or the media library', function (Role $role): void {
    $media = croppablePhoto();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->postJson('made-cms/media/'.$media->id.'/crop', cropBody())
        ->assertOk();
})->with([Role::Owner, Role::SeoSpecialist, Role::ContentManager]);

it('forbids it to an editor who opens neither and answers 401 to a guest', function (): void {
    $media = croppablePhoto();

    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->postJson('made-cms/media/'.$media->id.'/crop', cropBody())
        ->assertForbidden();

    auth('made-cms')->logout();

    $this->postJson('made-cms/media/'.$media->id.'/crop', cropBody())->assertUnauthorized();

    expect($media->refresh()->crop)->toBeNull();
});
