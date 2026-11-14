<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\CropImage;
use Made\CmsPackage\Content\Actions\RenameMedia;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\CropArea;
use Made\CmsPackage\Content\Data\CropMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Events\MediaRenamed;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

function renamed(string $path, string $filename = 'teamfoto.jpg'): Media
{
    return UploadMedia::run(new UploadedMedia($path, $filename));
}

it('renames a photo as a slug and moves it within its folder', function (): void {
    $disk = Storage::disk('public');
    $media = renamed(photo(400, 300));
    $former = $media->path;
    $contents = $disk->get($former);
    $untouched = [$media->original_path, $media->thumb_path];

    $media = RenameMedia::run($media, 'Teamfoto Merwestad 4');

    expect($media->name)->toBe('teamfoto-merwestad-4')
        ->and($media->path)->toBe(dirname($former).'/teamfoto-merwestad-4.webp')
        ->and($disk->get($media->path))->toBe($contents)
        ->and($disk->exists($former))->toBeFalse()
        ->and([$media->original_path, $media->thumb_path])->toBe($untouched)
        ->and(Media::query()->findOrFail($media->id)->only('name', 'path'))->toBe(['name' => 'teamfoto-merwestad-4', 'path' => $media->path]);
});

it('renames a cropped photo within the folder of its crop', function (): void {
    $media = CropImage::run(renamed(photo(400, 300)), new CropMedia(new CropArea(0, 0, 200, 200)));
    $folder = dirname($media->path);

    $media = RenameMedia::run($media, 'vierkant');

    expect($media->path)->toBe("{$folder}/vierkant.webp")
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();
});

it('renames the file as it is now, so a crop made after the editor opened it stays', function (): void {
    $disk = Storage::disk('public');
    $opened = renamed(photo(400, 300));
    $cropped = CropImage::run(Media::query()->findOrFail($opened->id), new CropMedia(new CropArea(0, 0, 200, 200)));

    $media = RenameMedia::run($opened, 'vierkant');
    $size = getimagesizefromstring((string) $disk->get($media->path));

    expect($media->path)->toBe(dirname($cropped->path).'/vierkant.webp')
        ->and($media->crop)->toBe($cropped->crop)
        ->and([$size[0] ?? null, $size[1] ?? null])->toBe([200, 200])
        ->and($disk->allFiles())->toEqualCanonicalizing([$media->path, (string) $cropped->thumb_path, (string) $cropped->original_path]);
});

it('keeps the file when it is renamed away and back within one transaction', function (): void {
    $disk = Storage::disk('public');
    $media = renamed(photo(400, 300));
    $contents = $disk->get($media->path);

    $media = DB::transaction(fn (): Media => RenameMedia::run(RenameMedia::run($media, 'clubavond'), 'teamfoto'));

    expect($media->path)->toEndWith('/teamfoto.webp')
        ->and($disk->get($media->path))->toBe($contents)
        ->and($disk->allFiles())->toEqualCanonicalizing([$media->path, (string) $media->thumb_path, (string) $media->original_path]);
});

it('keeps the file the row holds again when renaming it back fails and the transaction rolls back', function (): void {
    $disk = Storage::disk('public');
    $media = renamed(photo(400, 300));

    try {
        DB::transaction(function () use ($media): void {
            $renamed = RenameMedia::run($media, 'clubavond');
            Media::updating(static fn (): never => throw new RuntimeException('The row cannot be stored.'));
            RenameMedia::run($renamed, 'teamfoto');
        });
        test()->fail('The transaction was committed.');
    } catch (RuntimeException) {
    }

    expect(Media::query()->findOrFail($media->id)->path)->toBe($media->path)
        ->and($disk->exists($media->path))->toBeTrue();
});

it('renames a file that was renamed after the editor opened it from the name it has now', function (): void {
    $disk = Storage::disk('public');
    $opened = renamed(photo(400, 300));
    $between = RenameMedia::run(Media::query()->findOrFail($opened->id), 'clubavond');

    $media = RenameMedia::run($opened, 'teamfoto');

    expect($media->path)->toBe(dirname($between->path).'/teamfoto.webp')
        ->and($disk->exists($media->path))->toBeTrue()
        ->and($disk->exists($between->path))->toBeFalse();
});

it('renames a file kept as it came in, with its own extension', function (Closure $file, string $extension): void {
    $media = renamed($file(), "bestand.{$extension}");

    $media = RenameMedia::run($media, 'Nieuwe naam');

    expect($media->path)->toEndWith("/nieuwe-naam.{$extension}")
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue();
})->with([
    'an svg' => [fn (): string => mediaFixture('logo.svg'), 'svg'],
    'a pdf' => [fn (): string => mediaFixture('wedstrijdschema.pdf'), 'pdf'],
]);

it('refuses a name without letters or digits and changes nothing', function (string $name): void {
    $media = renamed(photo(400, 300));
    $files = Storage::disk('public')->allFiles();

    try {
        RenameMedia::run($media, $name);
        test()->fail('The name was stored.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['name' => ['Geef het bestand een naam met letters of cijfers.']]);
    }

    expect(Media::query()->findOrFail($media->id)->name)->toBe('teamfoto')
        ->and(Storage::disk('public')->allFiles())->toBe($files);
})->with(['empty' => [''], 'signs' => ['?!']]);

it('leaves a file alone when its name stays the same', function (): void {
    Event::fake([MediaRenamed::class]);
    $media = renamed(photo(400, 300));
    $files = Storage::disk('public')->allFiles();

    RenameMedia::run($media, 'Teamfoto');

    expect(Storage::disk('public')->allFiles())->toBe($files);
    Event::assertNotDispatched(MediaRenamed::class);
});

it('tells that a file was renamed, after the commit', function (): void {
    Event::fake([MediaRenamed::class]);
    $media = renamed(photo(400, 300));

    RenameMedia::run($media, 'clubavond');

    Event::assertDispatched(MediaRenamed::class, fn (MediaRenamed $event): bool => $event->media->is($media) && $event->media->name === 'clubavond');
});

it('keeps the file of a committed name when something after the commit fails', function (): void {
    $media = renamed(photo(400, 300));
    Event::listen(MediaRenamed::class, static fn (): never => throw new RuntimeException('A listener failed.'));

    expect(fn (): Media => RenameMedia::run($media, 'clubavond'))->toThrow(RuntimeException::class);

    $stored = Media::query()->findOrFail($media->id);

    expect($stored->path)->toEndWith('/clubavond.webp')
        ->and(Storage::disk('public')->exists($stored->path))->toBeTrue();
});

it('keeps the former file when the row cannot be stored', function (): void {
    $media = renamed(photo(400, 300));
    $files = Storage::disk('public')->allFiles();
    Media::updating(static fn (): never => throw new RuntimeException('The row cannot be stored.'));

    expect(fn (): Media => RenameMedia::run($media, 'clubavond'))->toThrow(RuntimeException::class)
        ->and(Storage::disk('public')->allFiles())->toBe($files)
        ->and(Media::query()->findOrFail($media->id)->only('name', 'path'))->toBe($media->only('name', 'path'));
});

it('refuses a file that is gone, without copying', function (): void {
    $media = renamed(photo(400, 300));
    $files = Storage::disk('public')->allFiles();
    Media::query()->whereKey($media->id)->delete();

    expect(fn (): Media => RenameMedia::run($media, 'clubavond'))->toThrow(ModelNotFoundException::class)
        ->and(Storage::disk('public')->allFiles())->toBe($files);
});
