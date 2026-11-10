<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\RecognisedField;
use Made\CmsPackage\Content\Enums\ScanDoubt;
use Made\CmsPackage\Content\Enums\ScanRole;

/**
 * The first list the scan reads in the only strip of a page: where it is, the tag of an item, its doubts and per
 * item the fields by selector.
 *
 * @return array{selector: string, item: string|null, doubts: list<string>, items: list<array<string, list<mixed>>>}|null
 */
function listOf(string $strip): ?array
{
    foreach (recognise('<main>'.$strip.'</main>')->strips[0]->fields ?? [] as $field) {
        if ($field->role === ScanRole::Items) {
            return [
                'selector' => $field->selector,
                'item' => $field->item,
                'doubts' => ScanDoubt::values($field->doubts),
                'items' => array_map(static function (array $fields): array {
                    $item = [];

                    foreach ($fields as $itemField) {
                        $item[$itemField->selector] = fieldRow($itemField);
                    }

                    return $item;
                }, $field->items),
            ];
        }
    }

    return null;
}

function card(string $title, string $text): string
{
    return '<div class="kaart"><h3>'.$title.'</h3><p>'.$text.'</p></div>';
}

it('reads siblings with the same structure as a list, with the fields of every item', function (): void {
    $strip = '<section><h2>Over ons</h2><div class="grid">'.card('Alle niveaus', 'Een.').card('Gezelligheid', 'Twee.').card('Competitie', 'Drie.').'</div></section>';

    expect(fieldsOf($strip))->toHaveKeys(['h2', 'div'])
        ->and(listOf($strip))->toBe([
            'selector' => 'div',
            'item' => 'div',
            'doubts' => [],
            'items' => [
                ['h3' => ['heading', ['text' => 'Alle niveaus', 'tag' => 'h3']], 'p' => ['paragraph', 'Een.']],
                ['h3' => ['heading', ['text' => 'Gezelligheid', 'tag' => 'h3']], 'p' => ['paragraph', 'Twee.']],
                ['h3' => ['heading', ['text' => 'Competitie', 'tag' => 'h3']], 'p' => ['paragraph', 'Drie.']],
            ],
        ]);
});

it('reads a row of links as a list of buttons, also when their looks differ', function (): void {
    expect(listOf('<section><div class="knoppen"><a href="#lid" class="btn-primary">Lid worden</a><a href="#over" class="btn-secondary">Meer</a></div></section>'))
        ->toBe([
            'selector' => 'div',
            'item' => 'a',
            'doubts' => [],
            'items' => [
                ['' => ['button', ['text' => 'Lid worden', 'link' => ['url' => '#lid']]]],
                ['' => ['button', ['text' => 'Meer', 'link' => ['url' => '#over']]]],
            ],
        ]);
});

it('keeps a link with an icon apart from a link without one', function (): void {
    expect(fieldsOf('<section><div class="knoppen"><a href="#lid">Lid worden <svg><path d="M0 0"></path></svg></a><a href="#over">Meer</a></div></section>'))->toBe([
        'div > a' => ['button', ['text' => 'Lid worden', 'link' => ['url' => '#lid']]],
        'div > a:nth-of-type(2)' => ['button', ['text' => 'Meer', 'link' => ['url' => '#over']]],
    ]);
});

it('reads elements right after each other as separate words', function (): void {
    expect(listOf('<section><div class="avonden">'
        .'<div><h3>Dinsdag</h3><div class="tags"><span>Vrij spelen</span><span>competitie</span></div></div>'
        .'<div><h3>Donderdag</h3><div class="tags"><span>Jeugd</span><span>training</span></div></div>'
        .'</div></section>')['items'][0] ?? null)->toBe([
            'h3' => ['heading', ['text' => 'Dinsdag', 'tag' => 'h3']],
            'div' => ['list', "Vrij spelen\ncompetitie", ['nested_list']],
        ])
        ->and(fieldsOf('<section><p><span>Vrij</span><span>spelen</span></p></section>'))->toBe(['p' => ['paragraph', 'Vrij spelen']]);
});

it('takes items that differ a little as one list and doubts it', function (): void {
    $strip = '<section><div class="lijst">'
        .'<div class="item"><img src="/a.jpg" alt="A"><h3>Een</h3><p>Tekst.</p></div>'
        .'<div class="item"><h3>Twee</h3><p>Tekst.</p></div>'
        .'</div></section>';

    expect(listOf($strip))->toMatchArray([
        'doubts' => ['items_differ'],
        'items' => [
            ['img' => ['image', ['url' => '/a.jpg', 'alt' => 'A']], 'h3' => ['heading', ['text' => 'Een', 'tag' => 'h3']], 'p' => ['paragraph', 'Tekst.']],
            ['h3' => ['heading', ['text' => 'Twee', 'tag' => 'h3']], 'p' => ['paragraph', 'Tekst.']],
        ],
    ]);
});

it('takes items whose headings differ in level only as one list, without doubt', function (): void {
    $strip = '<section><div class="lijst">'
        .'<div class="item"><h3>Een</h3><p>Tekst.</p></div>'
        .'<div class="item"><h4>Twee</h4><p>Tekst.</p></div>'
        .'</div></section>';

    expect(listOf($strip))->toMatchArray([
        'doubts' => [],
        'items' => [
            ['h3' => ['heading', ['text' => 'Een', 'tag' => 'h3']], 'p' => ['paragraph', 'Tekst.']],
            ['h4' => ['heading', ['text' => 'Twee', 'tag' => 'h4']], 'p' => ['paragraph', 'Tekst.']],
        ],
    ]);
});

it('does not make a list of siblings that hold different things', function (): void {
    $strip = '<section><div class="wrap"><div class="reveal"><span>Lid worden</span></div><div class="reveal"><h2>Doe mee</h2></div><div class="reveal"><p>Kom spelen.</p></div></div></section>';

    expect(listOf($strip))->toBeNull()
        ->and(fieldsOf($strip))->toBe([
            'div > div > span' => ['text', 'Lid worden'],
            'div > div:nth-of-type(2) > h2' => ['heading', ['text' => 'Doe mee', 'tag' => 'h2']],
            'div > div:nth-of-type(3) > p' => ['paragraph', 'Kom spelen.'],
        ]);
});

it('never makes a list of paragraphs or headings', function (): void {
    expect(fieldsOf('<section><div><h3>Kop</h3><h3>Nog een kop</h3><p>Een.</p><p>Twee.</p></div></section>'))->toBe([
        'div > h3' => ['heading', ['text' => 'Kop', 'tag' => 'h3']],
        'div > h3:nth-of-type(2)' => ['heading', ['text' => 'Nog een kop', 'tag' => 'h3']],
        'div > p' => ['paragraph', 'Een.'],
        'div > p:nth-of-type(2)' => ['paragraph', 'Twee.'],
    ]);
});

it('passes empty siblings by: they never form a list and never break one', function (): void {
    $strip = '<section><div class="grid">'.card('Een', 'A.').'<div class="lijn"></div>'.card('Twee', 'B.').'<div class="schaal"><i></i><i></i><i></i></div></div></section>';

    expect(listOf($strip)['items'] ?? null)->toBe([
        ['h3' => ['heading', ['text' => 'Een', 'tag' => 'h3']], 'p' => ['paragraph', 'A.']],
        ['h3' => ['heading', ['text' => 'Twee', 'tag' => 'h3']], 'p' => ['paragraph', 'B.']],
    ]);
});

it('reads a repeat inside an item as a long text with a line per element', function (): void {
    $team = static fn (string $name, string $players): string => '<div class="team"><h3>'.$name.'</h3><div class="spelers">'.$players.'</div></div>';
    $player = static fn (string $name): string => '<span class="chip"><span class="initiaal">'.$name[0].'</span>'.$name.'</span>';

    $strip = '<section><div class="teams">'.$team('Merwestad 1', $player('Gert-Jan').$player('Stefan')).$team('Merwestad 2', $player('Jaap').$player('Gerrit').$player('Desmond')).'</div></section>';

    expect(listOf($strip)['items'] ?? null)->toBe([
        ['h3' => ['heading', ['text' => 'Merwestad 1', 'tag' => 'h3']], 'div' => ['list', "Gert-Jan\nStefan", ['nested_list']]],
        ['h3' => ['heading', ['text' => 'Merwestad 2', 'tag' => 'h3']], 'div' => ['list', "Jaap\nGerrit\nDesmond", ['nested_list']]],
    ]);
});

it('keeps the icons of the items with their classes', function (): void {
    $card = static fn (string $icon, string $title): string => '<div class="kaart"><div class="icoon"><svg class="lucide lucide-'.$icon.' h-5"><path d="M0 0"></path></svg></div><h3>'.$title.'</h3></div>';

    expect(listOf('<section><div class="grid">'.$card('users', 'Alle niveaus').$card('heart', 'Gezelligheid').'</div></section>')['items'] ?? null)->toBe([
        ['div > svg' => ['icon', ['lucide', 'lucide-users', 'h-5']], 'h3' => ['heading', ['text' => 'Alle niveaus', 'tag' => 'h3']]],
        ['div > svg' => ['icon', ['lucide', 'lucide-heart', 'h-5']], 'h3' => ['heading', ['text' => 'Gezelligheid', 'tag' => 'h3']]],
    ]);
});

it('reads a list of cards in a ul as a list, and a gallery as a list of images', function (): void {
    expect(listOf('<section><ul class="kaarten"><li><h3>A</h3><p>Een.</p></li><li><h3>B</h3><p>Twee.</p></li></ul></section>'))
        ->toMatchArray(['selector' => 'ul', 'item' => 'li'])
        ->and(listOf('<section><h2>Foto\'s</h2><div class="galerij"><img src="/1.jpg" alt="Een"><img src="/2.jpg" alt="Twee"></div></section>'))
        ->toMatchArray([
            'item' => 'img',
            'items' => [
                ['' => ['image', ['url' => '/1.jpg', 'alt' => 'Een']]],
                ['' => ['image', ['url' => '/2.jpg', 'alt' => 'Twee']]],
            ],
        ]);
});

it('keeps the fields of a list in their stored form when it travels', function (): void {
    $field = recognise('<main><section><div class="grid">'.card('Een', 'A.').card('Twee', 'B.').'</div></section></main>')->strips[0]->fields[0];

    expect(RecognisedField::fromArray($field->toArray()))->toEqual($field);
});
