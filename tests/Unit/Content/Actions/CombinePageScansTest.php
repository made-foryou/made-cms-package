<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\CombinePageScans;
use Made\CmsPackage\Content\Data\RecognisedPage;
use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Data\ScanType;

/**
 * The scan of a site whose pages have the given bodies, at /1, /2 and so on.
 */
function combine(string ...$bodies): ScanResult
{
    $pages = [];

    foreach (array_values($bodies) as $index => $body) {
        $pages[] = recognise($body, '/'.($index + 1));
    }

    return CombinePageScans::run($pages);
}

it('proposes a strip type per structure, named after its id or generic', function (): void {
    $scan = combine('<main><section id="over-ons"><h2>Over ons</h2><p>Wij zijn een club.</p></section><section class="relative py-8"><h2>Doe mee</h2><a href="/lid">Lid worden</a></section></main>');

    expect(array_map(static fn (ScanType $type): array => [$type->key, $type->label, $type->hint, $type->count], $scan->types))->toBe([
        ['over-ons', 'Over ons', '#over-ons', 1],
        ['strook-2', 'Strook 2', null, 1],
    ])
        ->and($scan->types[0]->certain())->toBeTrue()
        ->and($scan->types[1]->toArray()['doubts'])->toBe(['generic_name'])
        ->and(outline($scan->types[0]))->toBe(['kop: text', 'tekst: long_text'])
        ->and(outline($scan->types[1]))->toBe(['kop: text', 'knop: button'])
        ->and($scan->pages[0]->toArray())->toBe([
            'url' => '/1',
            'title' => 'Testpagina',
            'strips' => [
                ['type' => 'over-ons', 'selector' => '#over-ons', 'values' => ['kop' => 'Over ons', 'tekst' => 'Wij zijn een club.']],
                ['type' => 'strook-2', 'selector' => 'body > main > section:nth-of-type(2)', 'values' => ['kop' => 'Doe mee', 'knop' => ['text' => 'Lid worden', 'link' => ['url' => '/lid']]]],
            ],
        ]);
});

it('takes strips with the same structure on several pages as one type', function (): void {
    $teaser = static fn (string $intro, string $cards): string => '<main><section class="teaser"><h2>Nieuws</h2><p>'.$intro.'</p><div class="grid">'.$cards.'</div></section></main>';
    $card = static fn (string $title): string => '<div><h3>'.$title.'</h3><p>Bericht '.$title.'.</p><a href="/'.strtolower($title).'">Meer over '.$title.'</a></div>';

    $scan = combine($teaser('Het laatste nieuws.', $card('Een').$card('Twee')), $teaser('', $card('Drie').$card('Vier').$card('Vijf')));
    $type = $scan->types[0];

    expect($scan->types)->toHaveCount(1)
        ->and([$type->key, $type->count])->toBe(['teaser', 2])
        ->and(array_map(static fn (ScanField $field): array => [$field->key, $field->required], $type->fields))->toBe([['kop', true], ['tekst', false], ['items', true]])
        ->and([$type->fields[2]->item, $type->fields[2]->min, $type->fields[2]->max])->toBe(['div', 1, 3])
        ->and($scan->pages[1]->strips[0]->type)->toBe('teaser')
        ->and($scan->pages[1]->strips[0]->values)->not->toHaveKey('tekst')
        ->and($scan->pages[1]->strips[0]->values['items'][0])->toBe(['kop' => 'Drie', 'tekst' => 'Bericht Drie.', 'knop' => ['text' => 'Meer over Drie', 'link' => ['url' => '/drie']]]);
});

it('numbers the fields with the same name, in the language of the launcher', function (): void {
    $body = '<main><section><h2>Een</h2><h3>Twee</h3><p>Drie.</p></section></main>';

    expect(outline(combine($body)->types[0]))->toBe(['kop: text', 'kop_2: text', 'tekst: long_text']);

    config(['made-cms-package.launcher.locale' => 'en']);
    $scan = combine($body);

    expect($scan->types[0]->key)->toBe('strip-1')
        ->and($scan->types[0]->label)->toBe('Strip 1')
        ->and(outline($scan->types[0]))->toBe(['heading: text', 'heading_2: text', 'text: long_text']);
});

it('makes a choice of the icons that differ per item, and passes icons that are all the same by', function (): void {
    $card = static fn (string $icon, string $title): string => '<div class="kaart"><div class="icoon"><svg class="lucide lucide-'.$icon.' h-5"><path d="M0 0"></path></svg></div><h3>'.$title.'</h3><svg class="lucide lucide-arrow h-4"></svg></div>';

    $scan = combine('<main><section id="waarom"><div class="grid">'.$card('users', 'Alle niveaus').$card('heart', 'Gezelligheid').$card('trophy', 'Competitie').'</div></section></main>');
    $icon = $scan->types[0]->fields[0]->fields[0];

    expect(outline($scan->types[0]))->toBe([['items: items' => ['icoon: choice', 'kop: text']]])
        ->and($icon->options)->toBe(['users', 'heart', 'trophy'])
        ->and($icon->toArray()['doubts'])->toBe(['icon_choice'])
        ->and($icon->selector)->toBe('div > svg')
        ->and(array_column($scan->pages[0]->strips[0]->values['items'], 'icoon'))->toBe(['users', 'heart', 'trophy']);
});

it('passes text that every item shows the same by when it is a short label, and doubts it otherwise', function (): void {
    $team = static fn (string $name, string $url): string => '<div class="team"><h3>'.$name.'</h3><p>West - Senioren DUO</p><span class="tijd">19:30</span><p class="kop">Spelers</p><a href="'.$url.'">Bekijk uitslagen</a></div>';

    $scan = combine('<main><section id="teams"><div class="grid">'.$team('Merwestad 1', '/1').$team('Merwestad 2', '/2').$team('Merwestad 3', '/3').'</div></section></main>');
    $items = $scan->types[0]->fields[0];

    expect(outline($scan->types[0]))->toBe([['items: items' => ['kop: text', 'tekst: long_text', 'label: text', 'link: link']]])
        ->and($items->fields[1]->toArray()['doubts'])->toBe(['same_in_every_item'])
        ->and($items->fields[2]->toArray()['doubts'])->toBe(['same_in_every_item'])
        ->and($scan->pages[0]->strips[0]->values['items'][1])->toBe(['kop' => 'Merwestad 2', 'tekst' => 'West - Senioren DUO', 'label' => '19:30', 'link' => ['url' => '/2']]);
});

it('leaves a list out when every item is the same, and a type without fields', function (): void {
    $scan = combine('<main><section id="kop"><h2>Kop</h2></section><section id="meer"><div class="r"><div><a href="/x">Meer</a></div><div><a href="/x">Meer</a></div></div></section></main>');

    expect(array_map(static fn (ScanType $type): string => $type->key, $scan->types))->toBe(['kop'])
        ->and($scan->pages[0]->strips)->toHaveCount(1);
});

it('gives another structure with a name already taken a number', function (): void {
    $scan = combine('<main><section id="intro"><h2>Welkom</h2></section></main>', '<main><section id="intro"><p>Een andere opbouw.</p></section></main>');

    expect(array_map(static fn (ScanType $type): string => $type->key, $scan->types))->toBe(['intro', 'intro-2'])
        ->and($scan->pages[1]->strips[0]->type)->toBe('intro-2');
});

it('widens a field that holds a link on one page to rich text on every page', function (): void {
    $strip = static fn (string $text): string => '<main><section class="tekst"><h2>Over</h2><p>'.$text.'</p></section></main>';

    $scan = combine($strip('Gewoon & simpel.'), $strip('Lees <a href="/meer">meer</a>.'));

    expect(outline($scan->types[0]))->toBe(['kop: text', 'tekst: rich_text'])
        ->and($scan->pages[0]->strips[0]->values['tekst'])->toBe('<p>Gewoon &amp; simpel.</p>')
        ->and($scan->pages[1]->strips[0]->values['tekst'])->toBe('<p>Lees <a href="/meer">meer</a>.</p>');
});

it('reads a page once, also when the scan sends it twice', function (): void {
    $page = recognise('<main><section id="a"><h2>A</h2></section></main>', '/a');

    expect(CombinePageScans::run([$page, $page])->toArray())->toBe(CombinePageScans::run([$page])->toArray())
        ->and(CombinePageScans::run([RecognisedPage::fromArray($page->toArray())])->toArray())->toBe(CombinePageScans::run([$page])->toArray());
});

it('combines no pages into an empty result', function (): void {
    expect(CombinePageScans::run([])->toArray())->toBe(['version' => 1, 'types' => [], 'pages' => []]);
});
