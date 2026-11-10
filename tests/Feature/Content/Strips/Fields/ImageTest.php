<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Strips\Fields\Image;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;

/**
 * @param  array<string, mixed>  $values
 * @return array<string, list<string>>
 */
function imageErrors(array $values, bool $required = false): array
{
    $image = Image::make('foto')->label('Achtergrondfoto');
    $type = new InlineStripType('hero', [$required ? $image->required() : $image]);

    return Validator::make($values, $type->rules(), $type->messages(), $type->attributes())->errors()->toArray();
}

it('accepts an image of the library, also by an id as text', function (): void {
    $media = Media::factory()->create();

    expect(imageErrors(['foto' => $media->id]))->toBe([])
        ->and(imageErrors(['foto' => (string) $media->id]))->toBe([]);
});

it('refuses a file that does not exist', function (): void {
    expect(imageErrors(['foto' => 999]))->toBe(['foto' => ['De gekozen afbeelding bij Achtergrondfoto bestaat niet meer.']]);
});

it('refuses a file that is no image', function (Closure $media): void {
    expect(imageErrors(['foto' => $media()]))->toBe(['foto' => ['Het gekozen bestand bij Achtergrondfoto is geen afbeelding.']]);
})->with([
    'a document' => [fn (): int => Media::factory()->document()->create()->id],
    'a video' => [fn (): int => Media::factory()->video()->create()->id],
]);

it('refuses a value that is no id of a file', function (mixed $value): void {
    expect(imageErrors(['foto' => $value]))->toBe(['foto' => ['Kies bij Achtergrondfoto een afbeelding uit Media.']]);
})->with([
    'a text' => ['teamfoto.jpg'],
    'a negative number' => [-1],
    'a decimal number' => [1.5],
    'an address and an alt text' => [['url' => '/photos/hero.jpg', 'alt' => '']],
]);

it('leaves an optional image empty and asks for a required one', function (): void {
    expect(imageErrors(['foto' => null]))->toBe([])
        ->and(imageErrors([]))->toBe([])
        ->and(imageErrors(['foto' => null], required: true))->toBe(['foto' => ['Vul Achtergrondfoto in.']]);
});

it('checks an image in every item of a list', function (): void {
    $media = Media::factory()->create();
    $field = Items::make('kaarten', [Text::make('titel'), Image::make('icoon')]);

    $errors = Validator::make(
        ['kaarten' => [['titel' => 'Een', 'icoon' => $media->id], ['titel' => 'Twee', 'icoon' => 999]]],
        $field->rules('kaarten'),
        [],
        $field->attributes('kaarten'),
    )->errors()->toArray();

    expect($errors)->toBe(['kaarten.1.icoon' => ['De gekozen afbeelding bij Icoon bestaat niet meer.']]);
});
