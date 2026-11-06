<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Made\CmsPackage\Content\Actions\CombinePageScans;
use Made\CmsPackage\Content\Actions\RecognisePage;
use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScannedPage;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Data\ScanType;
use Made\CmsPackage\Content\Scan\Html;

/*
| The scan of the live TTV Merwestad home (home.html) against what the developer
| wrote by hand when coupling it (#17): strips/*.php.stub and content.json. On
| the main lines the scan proposes the same: the same strips in the same order,
| the same lists with their items, mostly the same field types. Names may differ
| without the AI step, and so may what only the design shows (a day as a choice,
| the style of a button, a heading in two colours).
*/

function ttvScan(): ScanResult
{
    $html = (string) file_get_contents(ttvFixture('home.html'));

    return CombinePageScans::run([RecognisePage::run(new ScannedPage('/', $html))]);
}

/**
 * @return array<string, ScanType>
 */
function ttvTypes(): array
{
    $types = [];

    foreach (ttvScan()->types as $type) {
        $types[$type->key] = $type;
    }

    return $types;
}

it('finds the eight strips of the home in their order, as the developer coupled them', function (): void {
    $scan = ttvScan();
    $handwritten = array_column(ttvContent()['strips'], 'type');

    expect($scan->pages)->toHaveCount(1)
        ->and($scan->pages[0]->url)->toBe('/')
        ->and($scan->pages[0]->title)->toBe('Welkom - TTV Merwestad')
        ->and(array_column($scan->pages[0]->toArray()['strips'], 'type'))->toBe([
            'strook-1', 'strook-2', 'over-ons', 'trainingstijden', 'teams', 'locatie', 'lid-worden', 'contact',
        ])
        ->and(array_slice(array_column($scan->pages[0]->toArray()['strips'], 'type'), 2))->toBe(array_slice($handwritten, 2))
        ->and(array_unique(array_map(static fn (ScanType $type): string => $type->fingerprint, $scan->types)))->toHaveCount(8);
});

it('proposes the fields of every strip on the main lines of the handwritten types', function (): void {
    expect(array_map(outline(...), ttvTypes()))->toBe([
        'strook-1' => ['afbeelding: image', 'label: text', 'kop: text', 'tekst: long_text', 'knop: button', 'knop_2: button'],
        'strook-2' => [['items: items' => ['label: text', 'label_2: text']]],
        'over-ons' => ['label: text', 'kop: text', 'tekst: long_text', ['items: items' => ['icoon: choice', 'kop: text', 'tekst: long_text']]],
        'trainingstijden' => ['label: text', 'kop: text', 'tekst: long_text', ['items: items' => ['kop: text', 'label: text', 'label_2: text', 'lijst: long_text']], 'tekst_2: long_text'],
        'teams' => ['label: text', 'kop: text', 'tekst: long_text', ['items: items' => ['link: link', 'kop: text', 'tekst: long_text', 'lijst: long_text', 'lijst_2: long_text']]],
        'locatie' => ['label: text', 'kop: text', 'tekst: long_text', 'afbeelding: image', 'knop: button', ['items: items' => ['icoon: choice', 'kop: text', 'tekst: long_text']]],
        'lid-worden' => ['label: text', 'kop: text', 'tekst: long_text', 'knop: button', 'knop_2: button'],
        'contact' => ['label: text', 'kop: text', 'tekst: long_text', 'label_2: text', 'knop: button'],
    ]);
});

it('reads the lists with every item and makes a choice of the icons', function (): void {
    $types = ttvTypes();
    $values = [];

    foreach (ttvScan()->pages[0]->strips as $strip) {
        $values[$strip->type] = $strip->values;
    }

    $list = static fn (string $type): ScanField => array_values(array_filter($types[$type]->fields, static fn (ScanField $field): bool => $field->type === 'items'))[0];

    expect(array_map(static fn (string $type): int => count($values[$type]['items']), ['strook-2', 'over-ons', 'trainingstijden', 'teams', 'locatie']))->toBe([4, 3, 2, 3, 3])
        ->and([$list('strook-2')->min, $list('strook-2')->max])->toBe([1, 4])
        ->and($list('over-ons')->fields[0]->options)->toBe(['users', 'heart', 'trophy'])
        ->and($list('locatie')->fields[0]->options)->toBe(['building2', 'car', 'map-pin'])
        ->and(array_column($values['over-ons']['items'], 'icoon'))->toBe(['users', 'heart', 'trophy'])
        ->and(array_column($values['teams']['items'], 'lijst_2'))->toBe(["Gert-Jan\nStefan", "Jaap\nGerrit\nDesmond\nArjan", "Rob\nRuud\nHenk\nAbdol Majid"])
        ->and(array_column($values['trainingstijden']['items'], 'lijst'))->toBe(["Vrij spelen\ncompetitie", "Vrij spelen\ncompetitie"])
        ->and($list('trainingstijden')->fields[1]->required)->toBeFalse();
});

it('reads the values the developer stored by hand', function (): void {
    $content = [];
    $values = [];

    foreach (ttvContent()['strips'] as $strip) {
        $content[$strip['type']] = $strip['values'];
    }

    foreach (ttvScan()->pages[0]->strips as $strip) {
        $values[$strip->type] = $strip->values;
    }

    expect($values['lid-worden']['knop'])->toEqual($content['lid-worden']['knop'])
        ->and($values['lid-worden']['knop_2'])->toEqual($content['lid-worden']['tweede_knop'])
        ->and($values['locatie']['knop'])->toEqual($content['locatie']['kaart_knop'])
        ->and($values['over-ons']['kop'])->toBe($content['over-ons']['kop'])
        ->and($values['contact']['tekst'])->toBe($content['contact']['intro'])
        ->and(array_column($values['teams']['items'], 'link'))->toEqual(array_column($content['teams']['teams'], 'uitslagen'))
        ->and(array_column($values['strook-2']['items'], 'label_2'))->toBe(array_column($content['cijfers']['cijfers'], 'label'));
});

it('takes nothing from the navigation, the footer, the form or the texts of the design', function (): void {
    $json = json_encode(array_column(ttvScan()->toArray()['pages'][0]['strips'], 'values'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    expect($json)->not->toContain('Alle rechten voorbehouden')
        ->not->toContain('Gemaakt door')
        ->not->toContain('Je volledige naam')
        ->not->toContain('Verstuur bericht')
        ->not->toContain('"Spelers"')
        ->not->toContain('Bekijk uitslagen')
        ->not->toContain('Onze teams","Locatie');
});

it('doubts what it cannot be sure of', function (): void {
    $types = ttvTypes();

    expect($types['strook-1']->toArray()['doubts'])->toBe(['generic_name'])
        ->and($types['strook-1']->fields[0]->toArray()['doubts'])->toBe(['decorative_image'])
        ->and($types['contact']->toArray()['doubts'])->toBe(['contains_form'])
        ->and($types['over-ons']->certain())->toBeTrue();
});

it('finds every strip and every field back in the page by its selector', function (): void {
    $document = Html::parse((string) file_get_contents(ttvFixture('home.html')));
    $sections = $document->querySelectorAll('section');
    $types = ttvTypes();

    foreach (ttvScan()->pages[0]->strips as $index => $strip) {
        $root = $document->querySelector($strip->selector);

        expect($root)->toBe($sections[$index]);

        foreach ($types[$strip->type]->fields as $field) {
            $element = $field->selector === '' ? $root : $document->querySelector($strip->selector.' > '.$field->selector);
            $value = $strip->values[$field->key] ?? null;

            expect($element)->not->toBeNull();

            if (is_string($value) && in_array($field->type, ['text', 'long_text'], true)) {
                expect(str_replace("\n", ' ', $value))->toBe(Html::text($element));
            }
        }
    }
});

it('proposes valid strip types that accept what it read', function (): void {
    $scan = ttvScan();
    $types = scanStripTypes($scan);

    foreach ($scan->pages[0]->strips as $strip) {
        $type = $types->find($strip->type);

        expect(Validator::make($strip->values, $type->rules(), [], $type->attributes())->passes())->toBeTrue();
    }
});

it('keeps its result', function (): void {
    expect(scanJson(ttvScan()))->toMatchSnapshot();
});
