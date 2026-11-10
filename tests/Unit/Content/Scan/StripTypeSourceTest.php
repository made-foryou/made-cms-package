<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScanType;
use Made\CmsPackage\Content\Scan\ScannedStripType;
use Made\CmsPackage\Content\Scan\StripTypeSource;

/**
 * @param  list<ScanField>  $fields
 */
function sourceOf(string $key, string $label, array $fields, string $namespace = 'App\\Cms\\Strips'): string
{
    return StripTypeSource::of(
        ScannedStripType::from(new ScanType($key, $label, null, 'fingerprint', 1, $fields)),
        $namespace,
        StripTypeSource::className($key),
    );
}

/**
 * A proposal of the scan with every kind of field, a list, a choice and an image.
 */
function overOnsSource(): string
{
    return sourceOf('over-ons', 'Over ons', [
        new ScanField('afbeelding', 'image', 'img'),
        new ScanField('label', 'text', 'span'),
        new ScanField('kop', 'heading', 'h2', tag: 'h2'),
        new ScanField('kop_2', 'heading', 'h1', required: false, tag: 'h1'),
        new ScanField('tekst', 'long_text', 'p', required: false),
        new ScanField('inhoud', 'rich_text', 'div', required: false),
        new ScanField('knop', 'button', 'a'),
        new ScanField('link', 'link', 'a:nth-of-type(2)', required: false),
        new ScanField('items', 'items', 'div', item: 'article', min: 1, max: 3, fields: [
            new ScanField('icoon', 'choice', 'svg', options: ['users', 'heart', 'trophy']),
            new ScanField('kop', 'heading', 'h3', tag: 'h3'),
            new ScanField('tekst', 'long_text', 'p', required: false),
            new ScanField('foto', 'image', 'img'),
        ]),
        new ScanField('knoppen', 'items', 'div:nth-of-type(2)', item: 'a', min: 1, max: 2, fields: [new ScanField('knop', 'button', '')]),
    ]);
}

it('writes a proposal as a strip type class in the style of the site', function (): void {
    expect(overOnsSource())->toBe(<<<'PHP'
        <?php

        namespace App\Cms\Strips;

        use Made\CmsPackage\Content\Strips\Fields\Button;
        use Made\CmsPackage\Content\Strips\Fields\Choice;
        use Made\CmsPackage\Content\Strips\Fields\Heading;
        use Made\CmsPackage\Content\Strips\Fields\Items;
        use Made\CmsPackage\Content\Strips\Fields\Link;
        use Made\CmsPackage\Content\Strips\Fields\LongText;
        use Made\CmsPackage\Content\Strips\Fields\RichText;
        use Made\CmsPackage\Content\Strips\Fields\Text;
        use Made\CmsPackage\Content\Strips\StripType;

        final class OverOns extends StripType
        {
            public function key(): string
            {
                return 'over-ons';
            }

            public function label(): string
            {
                return 'Over ons';
            }

            public function fields(): array
            {
                // afbeelding: wacht op het veldtype Afbeelding.
                // items.foto: wacht op het veldtype Afbeelding.
                return [
                    Text::make('label')->required(),
                    Heading::make('kop')->required(),
                    Heading::make('kop_2')->tag('h1'),
                    LongText::make('tekst'),
                    RichText::make('inhoud'),
                    Button::make('knop')->required(),
                    Link::make('link'),
                    Items::make('items', [
                        Choice::make('icoon', ['users' => 'Users', 'heart' => 'Heart', 'trophy' => 'Trophy'])->required(),
                        Heading::make('kop')->tag('h3')->required(),
                        LongText::make('tekst'),
                    ])->max(3)->required(),
                    Items::make('knoppen', [Button::make('knop')->required()])->max(2)->required(),
                ];
            }
        }

        PHP);
});

it('gives a heading its level only when it is not the default level', function (): void {
    expect(sourceOf('kop', 'Kop', [new ScanField('kop', 'heading', 'h2', tag: 'h2'), new ScanField('ondertitel', 'heading', 'p', tag: 'span')]))
        ->toContain("Heading::make('kop')->required(),")
        ->toContain("Heading::make('ondertitel')->tag('span')->required(),")
        ->and(sourceOf('kop', 'Kop', [new ScanField('kop', 'heading', 'h2')]))->toContain("Heading::make('kop')->required(),");
});

it('leaves the label out when the class name reads as it', function (): void {
    $source = sourceOf('hero', 'Hero', [new ScanField('kop', 'text', 'h1')]);

    expect($source)->not->toContain('function label()')
        ->and($source)->toContain("final class Hero extends StripType\n{\n    public function key(): string");
});

it('puts a long choice on a line per option', function (): void {
    $days = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];

    expect(sourceOf('dagen', 'Dagen', [new ScanField('dag', 'choice', 'span', options: $days)]))->toContain(<<<'PHP'
                return [
                    Choice::make('dag', [
                        'maandag' => 'Maandag',
                        'dinsdag' => 'Dinsdag',
                        'woensdag' => 'Woensdag',
                        'donderdag' => 'Donderdag',
                        'vrijdag' => 'Vrijdag',
                        'zaterdag' => 'Zaterdag',
                        'zondag' => 'Zondag',
                    ])->required(),
                ];
        PHP);
});

it('keeps the values 0, 1, 2 of a choice a list', function (): void {
    expect(sourceOf('stand', 'Stand', [new ScanField('aantal', 'choice', 'span', options: ['0', '1', '2'])]))
        ->toContain("Choice::make('aantal', ['0', '1', '2'])->required(),");
});

it('escapes quotes and backslashes in texts', function (): void {
    expect(sourceOf('hoek', "Pieter's \\ hoek", [new ScanField('kop', 'text', 'h2')]))
        ->toContain("return 'Pieter\\'s \\\\ hoek';");
});

it('writes a type whose fields all wait for the image field with an empty list', function (): void {
    expect(sourceOf('foto', 'Foto', [new ScanField('afbeelding', 'image', 'img')]))->toContain(<<<'PHP'
            public function fields(): array
            {
                // afbeelding: wacht op het veldtype Afbeelding.
                return [];
            }
        PHP)->not->toContain('use Made\CmsPackage\Content\Strips\Fields');
});

it('names the class after the key, with Strip behind a name PHP or the imports already use', function (string $key, string $class): void {
    expect(StripTypeSource::className($key))->toBe($class);
})->with([
    ['over-ons', 'OverOns'],
    ['strook-1', 'Strook1'],
    ['lid-worden', 'LidWorden'],
    ['a', 'A'],
    ['list', 'ListStrip'],
    ['default', 'DefaultStrip'],
    ['match', 'MatchStrip'],
    ['enum', 'EnumStrip'],
    ['string', 'StringStrip'],
    ['text', 'TextStrip'],
    ['long-text', 'LongTextStrip'],
    ['heading', 'HeadingStrip'],
    ['items', 'ItemsStrip'],
    ['field', 'FieldStrip'],
    ['strip-type', 'StripTypeStrip'],
]);

it('writes the classes of the TTV Merwestad home', function (): void {
    expect(sourcesText(scanSources(ttvScan())))->toMatchSnapshot();
});

it('writes classes that Pint leaves as they are', function (): void {
    $sources = [
        ...scanSources(ttvScan()),
        'OverOns2.php' => overOnsSource(),
        'Hoek.php' => sourceOf('hoek', "Pieter's \\ hoek", [new ScanField('kop', 'text', 'h2')]),
        'Foto.php' => sourceOf('foto', 'Foto', [new ScanField('afbeelding', 'image', 'img')]),
        'Dagen.php' => sourceOf('dagen', 'Dagen', [new ScanField('dag', 'choice', 'span', options: ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'])]),
    ];

    expect(pintAccepts($sources))->toBeTrue();
});
