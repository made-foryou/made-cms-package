<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Actions\OpenMediaDownload;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Exceptions\MediaFileMissing;
use Made\CmsPackage\Content\Models\Media;

beforeEach(function (): void {
    Storage::fake('public');
});

it('gives the original of a photo as it was uploaded, under the name of the file', function (): void {
    $path = photo(2400, 1600, 'png', 'Teamfoto.png');
    $media = UploadMedia::run(new UploadedMedia($path, 'Teamfoto.png'));

    $download = OpenMediaDownload::run($media);

    expect($download->filename)->toBe('teamfoto.png')
        ->and($download->mime)->toBe('image/png')
        ->and($download->size)->toBe(filesize($path))
        ->and(stream_get_contents($download->stream))->toBe(file_get_contents($path));

    fclose($download->stream);
});

it('gives a file kept as it came in itself', function (): void {
    $media = UploadMedia::run(new UploadedMedia(mediaFixture('wedstrijdschema.pdf'), 'Schema najaar.pdf'));

    $download = OpenMediaDownload::run($media);

    expect($download->filename)->toBe('schema-najaar.pdf')
        ->and($download->mime)->toBe('application/pdf')
        ->and(stream_get_contents($download->stream))->toBe(file_get_contents(mediaFixture('wedstrijdschema.pdf')));

    fclose($download->stream);
});

it('refuses a file that is not on the disk', function (array $config): void {
    Storage::fake('public', $config);
    $media = Media::factory()->create();

    OpenMediaDownload::run($media);
})->with([
    'a disk that answers false' => [[]],
    'a disk that throws' => [['throw' => true]],
])->throws(MediaFileMissing::class);
