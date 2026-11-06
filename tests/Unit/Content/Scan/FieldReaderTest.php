<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\ScanDoubt;

it('reads a heading as one line of text', function (): void {
    expect(fieldsOf('<section><h2 class="kop">Over <span class="accent">ons</span></h2></section>'))
        ->toBe(['h2' => ['heading', 'Over ons']]);
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
            'a > h3' => ['heading', 'Merwestad 1'],
            'a > p' => ['paragraph', 'Senioren'],
        ]);
});

it('reads a link without text as a link', function (): void {
    expect(fieldsOf('<section><h2>Volg ons</h2><a href="https://instagram.com/club"><svg><path d="M0 0"></path></svg></a></section>'))
        ->toBe([
            'h2' => ['heading', 'Volg ons'],
            'a' => ['link', ['url' => 'https://instagram.com/club']],
        ]);
});

it('passes buttons of the interface by', function (): void {
    expect(fieldsOf('<section><h2>Menu</h2><button type="button">Open</button></section>'))
        ->toBe(['h2' => ['heading', 'Menu']]);
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
        'h2' => ['heading', 'De zaal'],
        'img:nth-of-type(2)' => ['image', ['url' => '/sfeer.jpg', 'alt' => ''], ['decorative_image']],
        'div > img' => ['image', ['url' => '/foto.jpg', 'alt' => ''], ['decorative_image']],
        'picture' => ['image', ['url' => '/kaart.jpg', 'alt' => 'Kaart']],
        'div:nth-of-type(2)' => ['image', ['url' => '/achter.jpg', 'alt' => ''], ['background_image']],
        'div:nth-of-type(2) > h2' => ['heading', 'Op de foto'],
        'div:nth-of-type(3) > p' => ['paragraph', 'Verloop'],
    ]);
});

it('takes nothing from a form, and doubts the strip that holds one', function (): void {
    $strip = recognise('<main><section><h2>Contact</h2><form><label>Naam</label><input placeholder="Je naam"><button>Verstuur</button></form></section></main>')->strips[0];

    expect(array_map(fieldRow(...), $strip->fields))->toBe([['heading', 'Contact']])
        ->and($strip->doubts)->toBe([ScanDoubt::ContainsForm]);
});

it('passes hidden texts and icons outside a list by', function (): void {
    expect(fieldsOf('<section><span aria-hidden="true">1</span><svg class="lucide lucide-star"></svg><i class="fa fa-star"></i><h3>Team</h3></section>'))
        ->toBe(['h3' => ['heading', 'Team']]);
});

it('reads the text of a block next to the blocks in it', function (): void {
    expect(fieldsOf('<section><div>Vooraf <div><h3>Kop</h3><p>Tekst</p></div></div></section>'))
        ->toBe([
            'div' => ['text', 'Vooraf'],
            'div > div > h3' => ['heading', 'Kop'],
            'div > div > p' => ['paragraph', 'Tekst'],
        ]);
});

it('leaves a strip out when the scan reads no field in it', function (): void {
    expect(recognise('<main><section><h2>Kop</h2></section><section><form><p>Alleen een formulier</p></form><span>x</span></section></main>')->strips)
        ->toHaveCount(2)
        ->and(recognise('<main><section><h2>Kop</h2></section><div><button>Naar boven</button></div></main>')->strips)
        ->toHaveCount(1);
});
