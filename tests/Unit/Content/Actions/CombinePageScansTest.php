<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\CombinePageScans;
use Made\CmsPackage\Content\Data\RecognisedPage;
use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScanPage;
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
        ->and(outline($scan->types[0]))->toBe(['kop: heading', 'tekst: long_text'])
        ->and(outline($scan->types[1]))->toBe(['kop: heading', 'knop: button'])
        ->and($scan->pages[0]->toArray())->toBe([
            'url' => '/1',
            'title' => 'Testpagina',
            'strips' => [
                ['type' => 'over-ons', 'selector' => '#over-ons', 'values' => ['kop' => ['text' => 'Over ons', 'tag' => 'h2'], 'tekst' => 'Wij zijn een club.']],
                ['type' => 'strook-2', 'selector' => 'body > main > section:nth-of-type(2)', 'values' => ['kop' => ['text' => 'Doe mee', 'tag' => 'h2'], 'knop' => ['text' => 'Lid worden', 'link' => ['url' => '/lid']]]],
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
        ->and($scan->pages[1]->strips[0]->values['items'][0])->toBe(['kop' => ['text' => 'Drie', 'tag' => 'h3'], 'tekst' => 'Bericht Drie.', 'knop' => ['text' => 'Meer over Drie', 'link' => ['url' => '/drie']]]);
});

it('numbers the fields with the same name, in the language of the launcher', function (): void {
    $body = '<main><section><h2>Een</h2><h3>Twee</h3><p>Drie.</p></section></main>';

    expect(outline(combine($body)->types[0]))->toBe(['kop: heading', 'kop_2: heading', 'tekst: long_text']);

    config(['made-cms-package.launcher.locale' => 'en']);
    $scan = combine($body);

    expect($scan->types[0]->key)->toBe('strip-1')
        ->and($scan->types[0]->label)->toBe('Strip 1')
        ->and(outline($scan->types[0]))->toBe(['heading: heading', 'heading_2: heading', 'text: long_text']);
});

it('makes a choice of the icons that differ per item, and passes icons that are all the same by', function (): void {
    $card = static fn (string $icon, string $title): string => '<div class="kaart"><div class="icoon"><svg class="lucide lucide-'.$icon.' h-5"><path d="M0 0"></path></svg></div><h3>'.$title.'</h3><svg class="lucide lucide-arrow h-4"></svg></div>';

    $scan = combine('<main><section id="waarom"><div class="grid">'.$card('users', 'Alle niveaus').$card('heart', 'Gezelligheid').$card('trophy', 'Competitie').'</div></section></main>');
    $icon = $scan->types[0]->fields[0]->fields[0];

    expect(outline($scan->types[0]))->toBe([['items: items' => ['icoon: choice', 'kop: heading']]])
        ->and($icon->options)->toBe(['users', 'heart', 'trophy'])
        ->and($icon->toArray()['doubts'])->toBe(['icon_choice'])
        ->and($icon->selector)->toBe('div > svg')
        ->and(array_column($scan->pages[0]->strips[0]->values['items'], 'icoon'))->toBe(['users', 'heart', 'trophy']);
});

it('passes text that every item shows the same by when it is a short label, and doubts it otherwise', function (): void {
    $team = static fn (string $name, string $url): string => '<div class="team"><h3>'.$name.'</h3><p>West - Senioren DUO</p><span class="tijd">19:30</span><p class="kop">Spelers</p><a href="'.$url.'">Bekijk uitslagen</a></div>';

    $scan = combine('<main><section id="teams"><div class="grid">'.$team('Merwestad 1', '/1').$team('Merwestad 2', '/2').$team('Merwestad 3', '/3').'</div></section></main>');
    $items = $scan->types[0]->fields[0];

    expect(outline($scan->types[0]))->toBe([['items: items' => ['kop: heading', 'tekst: long_text', 'label: text', 'link: link']]])
        ->and($items->fields[1]->toArray()['doubts'])->toBe(['same_in_every_item'])
        ->and($items->fields[2]->toArray()['doubts'])->toBe(['same_in_every_item'])
        ->and($scan->pages[0]->strips[0]->values['items'][1])->toBe(['kop' => ['text' => 'Merwestad 2', 'tag' => 'h3'], 'tekst' => 'West - Senioren DUO', 'label' => '19:30', 'link' => ['url' => '/2']]);
});

it('passes a heading that every item shows the same by when it is a short label, and doubts a longer one', function (): void {
    $team = static fn (string $name, string $heading): string => '<div class="team"><h3>'.$name.'</h3><h4>'.$heading.'</h4><p>Over '.$name.'.</p></div>';
    $teams = static fn (string $heading): string => '<main><section id="teams"><div class="grid">'.$team('Een', $heading).$team('Twee', $heading).'</div></section></main>';

    $label = combine($teams('Spelers'))->types[0]->fields[0];
    $text = combine($teams('Wie er deze week spelen'))->types[0]->fields[0];

    expect(array_map(static fn (ScanField $field): string => $field->key, $label->fields))->toBe(['kop', 'tekst'])
        ->and(array_map(static fn (ScanField $field): array => [$field->key, $field->type, $field->required, $field->toArray()['doubts']], $text->fields))->toBe([
            ['kop', 'heading', true, []],
            ['kop_2', 'heading', true, ['same_in_every_item']],
            ['tekst', 'long_text', true, []],
        ]);
});

it('leaves a list out when every item is the same, and a type without fields', function (): void {
    $scan = combine('<main><section id="kop"><h2>Kop</h2></section><section id="meer"><div class="r"><div><a href="/x">Meer</a></div><div><a href="/x">Meer</a></div></div></section></main>');

    expect(array_map(static fn (ScanType $type): string => $type->key, $scan->types))->toBe(['kop'])
        ->and($scan->pages[0]->strips)->toHaveCount(1);
});

it('takes strips that differ in the level of their headings only as one type, with one field per heading', function (): void {
    $strip = static fn (string $label, string $heading): string => '<main><section class="tekst"><'.$label.'>Over ons</'.$label.'><'.$heading.'>Wie wij zijn</'.$heading.'><p>Een club.</p></section></main>';

    $scan = combine($strip('h2', 'h3'), $strip('h3', 'h4'), $strip('h2', 'h2'));

    expect($scan->types)->toHaveCount(1)
        ->and($scan->types[0]->count)->toBe(3)
        ->and(array_map(static fn (ScanField $field): array => [$field->key, $field->selector, $field->required], $scan->types[0]->fields))->toBe([
            ['kop', 'h2', true],
            ['kop_2', 'h3', true],
            ['tekst', 'p', true],
        ])
        ->and(array_map(static fn (ScanPage $page): mixed => $page->strips[0]->values['kop_2'], $scan->pages))->toBe([
            ['text' => 'Wie wij zijn', 'tag' => 'h3'],
            ['text' => 'Wie wij zijn', 'tag' => 'h4'],
            ['text' => 'Wie wij zijn', 'tag' => 'h2'],
        ]);
});

it('proposes a heading at the level most examples have, and doubts a heading whose examples differ', function (): void {
    $strip = static fn (string $tag): string => '<main><section class="tekst"><'.$tag.'>Over ons</'.$tag.'><p>Een club.</p></section></main>';

    $mixed = combine($strip('h3'), $strip('h2'), $strip('h3'), $strip('h3'))->types[0]->fields[0];
    $same = combine($strip('h1'), $strip('h1'))->types[0]->fields[0];

    expect([$mixed->type, $mixed->tag, $mixed->toArray()['doubts']])->toBe(['heading', 'h3', ['heading_levels_differ']])
        ->and([$same->type, $same->tag, $same->toArray()['doubts']])->toBe(['heading', 'h1', []]);
});

it('takes the highest heading when as many examples have each level', function (): void {
    $strip = static fn (string $tag): string => '<main><section class="tekst"><'.$tag.'>Over ons</'.$tag.'><p>Een club.</p></section></main>';

    expect(combine($strip('h4'), $strip('h3'))->types[0]->fields[0]->tag)->toBe('h3')
        ->and(combine($strip('h2'), $strip('h5'), $strip('h5'), $strip('h2'))->types[0]->fields[0]->tag)->toBe('h2');
});

it('lines the headings in the items of a list up by their place, whatever their level', function (): void {
    $card = static fn (string $tag, string $title): string => '<div class="kaart"><'.$tag.'>'.$title.'</'.$tag.'><p>Over '.$title.'.</p></div>';

    $scan = combine('<main><section id="kaarten"><div class="grid">'.$card('h3', 'Een').$card('h4', 'Twee').'</div></section></main>');
    $items = $scan->types[0]->fields[0];

    expect(array_map(static fn (ScanField $field): array => [$field->key, $field->required], $items->fields))->toBe([['kop', true], ['tekst', true]])
        ->and($items->doubts)->toBe([])
        ->and([$items->fields[0]->tag, $items->fields[0]->toArray()['doubts']])->toBe(['h3', ['heading_levels_differ']])
        ->and(array_column($scan->pages[0]->strips[0]->values['items'], 'kop'))->toBe([
            ['text' => 'Een', 'tag' => 'h3'],
            ['text' => 'Twee', 'tag' => 'h4'],
        ]);
});

it('gives another structure with a name already taken a number', function (): void {
    $scan = combine('<main><section id="intro"><h2>Welkom</h2></section></main>', '<main><section id="intro"><p>Een andere opbouw.</p></section></main>');

    expect(array_map(static fn (ScanType $type): string => $type->key, $scan->types))->toBe(['intro', 'intro-2'])
        ->and($scan->pages[1]->strips[0]->type)->toBe('intro-2');
});

it('widens a field that holds a link on one page to rich text on every page', function (): void {
    $strip = static fn (string $text): string => '<main><section class="tekst"><h2>Over</h2><p>'.$text.'</p></section></main>';

    $scan = combine($strip('Gewoon & simpel.'), $strip('Lees <a href="/meer">meer</a>.'));

    expect(outline($scan->types[0]))->toBe(['kop: heading', 'tekst: rich_text'])
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

it('proposes emphasis for a heading when one of its examples has an <em>, with the asterisks in its values', function (): void {
    $strip = static fn (string $heading): string => '<main><section class="tekst"><h2>'.$heading.'</h2><p>Een club.</p></section></main>';

    $scan = combine($strip('Over <em>ons</em>'), $strip('Wie wij zijn'));
    $plain = combine($strip('Over ons'), $strip('Wie wij zijn'))->types[0]->fields[0];

    expect([$scan->types[0]->fields[0]->type, $scan->types[0]->fields[0]->emphasis])->toBe(['heading', true])
        ->and(array_map(static fn (ScanPage $page): mixed => $page->strips[0]->values['kop'], $scan->pages))->toBe([
            ['text' => 'Over *ons*', 'tag' => 'h2'],
            ['text' => 'Wie wij zijn', 'tag' => 'h2'],
        ])
        ->and($plain->emphasis)->toBeFalse();
});
