<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Made\CmsPackage\Content\Enums\MediaKind;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Strips\Fields\File;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;

/**
 * @param  array<string, mixed>  $values
 * @param  list<MediaKind>  $kinds
 * @return array<string, list<string>>
 */
function fileErrors(array $values, array $kinds = [], bool $required = false): array
{
    $file = File::make('bestand')->label('Document');
    $file = $kinds === [] ? $file : $file->kinds(...$kinds);
    $type = new InlineStripType('downloads', [$required ? $file->required() : $file]);

    return Validator::make($values, $type->rules(), $type->messages(), $type->attributes())->errors()->toArray();
}

it('accepts a document or a video of the library, also by an id as text', function (): void {
    $document = Media::factory()->document()->create();
    $video = Media::factory()->video()->create();

    expect(fileErrors(['bestand' => $document->id]))->toBe([])
        ->and(fileErrors(['bestand' => (string) $document->id]))->toBe([])
        ->and(fileErrors(['bestand' => $video->id]))->toBe([]);
});

it('refuses a file that does not exist', function (): void {
    expect(fileErrors(['bestand' => missingId()]))->toBe(['bestand' => ['Het gekozen bestand bij Document bestaat niet meer.']]);
});

it('refuses a file of a kind it does not take, in the words of the kinds it takes', function (array $kinds, Closure $media, string $error): void {
    expect(fileErrors(['bestand' => $media()], $kinds))->toBe(['bestand' => [$error]]);
})->with([
    'an image for a document or a video' => [[], fn (): int => Media::factory()->create()->id, 'Het gekozen bestand bij Document is geen document of video.'],
    'a video for a document' => [[MediaKind::Document], fn (): int => Media::factory()->video()->create()->id, 'Het gekozen bestand bij Document is geen document.'],
    'a document for a video' => [[MediaKind::Video], fn (): int => Media::factory()->document()->create()->id, 'Het gekozen bestand bij Document is geen video.'],
]);

it('refuses a value that is no id of a file', function (mixed $value): void {
    expect(fileErrors(['bestand' => $value]))->toBe(['bestand' => ['Kies bij Document een bestand uit Media.']]);
})->with([
    'a text' => ['wedstrijdschema.pdf'],
    'a negative number' => [-1],
    'a decimal number' => [1.5],
    'an address' => [['url' => '/files/schema.pdf']],
]);

it('leaves an optional file empty and asks for a required one', function (): void {
    expect(fileErrors(['bestand' => null]))->toBe([])
        ->and(fileErrors([]))->toBe([])
        ->and(fileErrors(['bestand' => null], required: true))->toBe(['bestand' => ['Vul Document in.']]);
});

it('checks a file in every item of a list', function (): void {
    $media = Media::factory()->document()->create();
    $field = Items::make('bestanden', [Text::make('titel'), File::make('bestand')]);

    $errors = Validator::make(
        ['bestanden' => [['titel' => 'Een', 'bestand' => $media->id], ['titel' => 'Twee', 'bestand' => missingId()]]],
        $field->rules('bestanden'),
        [],
        $field->attributes('bestanden'),
    )->errors()->toArray();

    expect($errors)->toBe(['bestanden.1.bestand' => ['Het gekozen bestand bij Bestand bestaat niet meer.']]);
});
