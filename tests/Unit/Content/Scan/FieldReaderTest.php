<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\ScanDoubt;

it('reads a heading as one line of text', function (): void {
    expect(fieldsOf('<section><h2 class="kop">Over <span class="accent">ons</span></h2></section>'))
        ->toBe(['h2' => ['heading', ['text' => 'Over ons', 'tag' => 'h2']]]);
});

it('reads a paragraph with a line for every br', function (): void {
    expect(fieldsOf("<section><p>Kom<br>\n   eens   spelen</p></section>"))
        ->toBe(['p' => ['paragraph', "Kom\neens spelen"]]);
});

it('keeps a link in a paragraph as rich text in its stored form', function (): void {
    expect(fieldsOf('<section><p class="intro">Lees <a href="/meer" class="x">meer</a> over ons.</p></section>'))
        ->toBe(['p' => ['rich_text', '<p>Lees <a href="/meer">meer</a> over ons.</p>']]);
});

it('reads several paragraphs in one block as rich text', function (): void {
    expect(fieldsOf('<section><div class="prose"><p>Een <strong>twee</strong>.</p><p>Drie.</p></div></section>'))
        ->toBe(['div' => ['rich_text', '<p>Een <strong>twee</strong>.</p><p>Drie.</p>']]);
});

it('reads a list of plain lines as rich text', function (): void {
    expect(fieldsOf('<section><ul class="lijst"><li>Een</li><li><em>Twee</em></li></ul></section>'))
        ->toBe(['ul' => ['rich_text', '<ul><li>Een</li><li><em>Twee</em></li></ul>']]);
});

it('reads the text of an element where the element holds it, past empty decoration', function (): void {
    expect(fieldsOf('<section><div class="label"><span class="dot"></span>Sinds 1962</div><div class="getal"><span>60+</span></div></section>'))
        ->toBe([
            'div' => ['text', 'Sinds 1962'],
            'div:nth-of-type(2) > span' => ['text', '60+'],
        ]);
});

it('reads a link with a text of its own as a button and passes its icon by', function (): void {
    expect(fieldsOf('<section><a href="#lid-worden" class="btn">Lid worden <svg class="lucide"><path d="M0 0"></path></svg></a></section>'))
        ->toBe(['a' => ['button', ['text' => 'Lid worden', 'link' => ['url' => '#lid-worden']]]]);
});

it('reads a link around a card as a link and the fields in it', function (): void {
    expect(fieldsOf('<section><a href="https://ttapp.nl/poule/1"><span aria-hidden="true">1</span><h3>Merwestad 1</h3><p>Senioren</p></a></section>'))
        ->toBe([
            'a' => ['link', ['url' => 'https://ttapp.nl/poule/1']],
            'a > h3' => ['heading', ['text' => 'Merwestad 1', 'tag' => 'h3']],
            'a > p' => ['paragraph', 'Senioren'],
        ]);
});

it('reads a link without text as a link', function (): void {
    expect(fieldsOf('<section><h2>Volg ons</h2><a href="https://instagram.com/club"><svg><path d="M0 0"></path></svg></a></section>'))
        ->toBe([
            'h2' => ['heading', ['text' => 'Volg ons', 'tag' => 'h2']],
            'a' => ['link', ['url' => 'https://instagram.com/club']],
        ]);
});

it('passes buttons of the interface by', function (): void {
    expect(fieldsOf('<section><h2>Menu</h2><button type="button">Open</button></section>'))
        ->toBe(['h2' => ['heading', ['text' => 'Menu', 'tag' => 'h2']]]);
});

it('reads images, also hidden and in the background, and doubts the decorative ones', function (): void {
    $strip = '<section>'
        .'<img src="/zaal.jpg" alt="De zaal">'
        .'<h2>De zaal</h2>'
        .'<img src="/sfeer.jpg" alt="">'
        .'<div aria-hidden="true" class="laag"><img src="/foto.jpg" alt=""><span>Versiering</span></div>'
        .'<picture><source srcset="/kaart.webp"><img src="/kaart.jpg" alt="Kaart"></picture>'
        .'<div style="background-image: url(\'/achter.jpg\')"><h2>Op de foto</h2></div>'
        .'<div style="background: linear-gradient(red, blue)"><p>Verloop</p></div>'
        .'</section>';

    expect(fieldsOf($strip))->toBe([
        'img' => ['image', ['url' => '/zaal.jpg', 'alt' => 'De zaal']],
        'h2' => ['heading', ['text' => 'De zaal', 'tag' => 'h2']],
        'img:nth-of-type(2)' => ['image', ['url' => '/sfeer.jpg', 'alt' => ''], ['decorative_image']],
        'div > img' => ['image', ['url' => '/foto.jpg', 'alt' => ''], ['decorative_image']],
        'picture' => ['image', ['url' => '/kaart.jpg', 'alt' => 'Kaart']],
        'div:nth-of-type(2)' => ['image', ['url' => '/achter.jpg', 'alt' => ''], ['background_image']],
        'div:nth-of-type(2) > h2' => ['heading', ['text' => 'Op de foto', 'tag' => 'h2']],
        'div:nth-of-type(3) > p' => ['paragraph', 'Verloop'],
    ]);
});

it('takes nothing from a form, and doubts the strip that holds one', function (): void {
    $strip = recognise('<main><section><h2>Contact</h2><form><label>Naam</label><input placeholder="Je naam"><button>Verstuur</button></form></section></main>')->strips[0];

    expect(array_map(fieldRow(...), $strip->fields))->toBe([['heading', ['text' => 'Contact', 'tag' => 'h2']]])
        ->and($strip->doubts)->toBe([ScanDoubt::ContainsForm]);
});

it('passes hidden texts and icons outside a list by', function (): void {
    expect(fieldsOf('<section><span aria-hidden="true">1</span><svg class="lucide lucide-star"></svg><i class="fa fa-star"></i><h3>Team</h3></section>'))
        ->toBe(['h3' => ['heading', ['text' => 'Team', 'tag' => 'h3']]]);
});

it('reads the text of a block next to the blocks in it', function (): void {
    expect(fieldsOf('<section><div>Vooraf <div><h3>Kop</h3><p>Tekst</p></div></div></section>'))
        ->toBe([
            'div' => ['text', 'Vooraf'],
            'div > div > h3' => ['heading', ['text' => 'Kop', 'tag' => 'h3']],
            'div > div > p' => ['paragraph', 'Tekst'],
        ]);
});

it('leaves a strip out when the scan reads no field in it', function (): void {
    expect(recognise('<main><section><h2>Kop</h2></section><section><form><p>Alleen een formulier</p></form><span>x</span></section></main>')->strips)
        ->toHaveCount(2)
        ->and(recognise('<main><section><h2>Kop</h2></section><div><button>Naar boven</button></div></main>')->strips)
        ->toHaveCount(1);
});

it('reads an <em> in a heading as emphasis between asterisks', function (string $heading, string $text): void {
    $field = recognise('<main><section>'.$heading.'</section></main>')->strips[0]->fields[0];

    expect([$field->role->value, $field->value, $field->emphasis])->toBe(['heading', ['text' => $text, 'tag' => 'h2'], true]);
})->with([
    'one word' => ['<h2>Jullie dag in <em>vertrouwde</em> handen</h2>', 'Jullie dag in *vertrouwde* handen'],
    'inside a word' => ['<h2>Privacy<em>verklaring</em></h2>', 'Privacy*verklaring*'],
    'more than one piece' => ['<h2><em>Samen</em> spelen, <em class="accent">samen winnen</em></h2>', '*Samen* spelen, *samen winnen*'],
    'spaces inside the em' => ['<h2>Jullie dag in<em> vertrouwde </em>handen</h2>', 'Jullie dag in *vertrouwde* handen'],
    'markup inside the em' => ['<h2>Jullie <em><strong>vertrouwde</strong>  dag</em></h2>', 'Jullie *vertrouwde dag*'],
]);

it('reads other markup in a heading, and an empty <em>, as plain text without emphasis', function (string $heading, string $text): void {
    $field = recognise('<main><section>'.$heading.'</section></main>')->strips[0]->fields[0];

    expect([$field->value, $field->emphasis])->toBe([['text' => $text, 'tag' => 'h2'], false]);
})->with([
    'a span' => ['<h2>Over <span class="accent">ons</span></h2>', 'Over ons'],
    'italic and bold' => ['<h2>Over <i>ons</i> en <strong>jou</strong></h2>', 'Over ons en jou'],
    'an empty em' => ['<h2>Over ons<em> </em></h2>', 'Over ons'],
    'asterisks of the site' => ['<h2>Sterren * en meer</h2>', 'Sterren * en meer'],
]);

it('reads an <em> outside a heading as plain text', function (): void {
    expect(fieldsOf('<section><h2>Kop</h2><span class="label">Sinds <em>1962</em></span></section>'))
        ->toMatchArray(['span' => ['text', 'Sinds 1962']]);
});
