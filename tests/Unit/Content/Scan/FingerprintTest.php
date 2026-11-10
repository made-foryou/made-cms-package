<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Scan\Fingerprint;
use Made\CmsPackage\Content\Scan\Html;

function fingerprintOf(string $strip): string
{
    $section = Html::parse(scanDocument($strip))->querySelector('body > *');

    return Fingerprint::of($section);
}

it('is a hash of the structure', function (): void {
    expect(fingerprintOf('<section><h2>Kop</h2><p>Tekst</p></section>'))->toMatch('/^[0-9a-f]{32}$/');
});

it('leaves texts, attributes, icons and decoration out', function (): void {
    expect(fingerprintOf('<section id="a" class="x"><h2>Kop</h2><p>Tekst</p></section>'))
        ->toBe(fingerprintOf('<section><div aria-hidden="true"><span></span></div><h2 class="y">Andere kop <svg><path></path></svg></h2><p>Andere tekst</p><script>1</script></section>'));
});

it('differs when the structure differs', function (): void {
    expect(fingerprintOf('<section><h2>Kop</h2><p>Tekst</p></section>'))
        ->not->toBe(fingerprintOf('<section><h2>Kop</h2><div><p>Tekst</p></div></section>'))
        ->not->toBe(fingerprintOf('<section><p>Tekst</p></section>'));
});

it('leaves the level of a heading out: the level is content', function (): void {
    expect(fingerprintOf('<section><h2>Kop</h2><p>Tekst</p></section>'))
        ->toBe(fingerprintOf('<section><h3>Kop</h3><p>Tekst</p></section>'))
        ->toBe(fingerprintOf('<section><h1>Kop</h1><p>Tekst</p></section>'));
});

it('counts every heading of a row, whatever their levels', function (): void {
    expect(fingerprintOf('<section><h2>Label</h2><h3>Kop</h3></section>'))
        ->not->toBe(fingerprintOf('<section><h2>Kop</h2></section>'))
        ->toBe(fingerprintOf('<section><h3>Label</h3><h4>Kop</h4></section>'))
        ->toBe(fingerprintOf('<section><h2>Label</h2><h2>Kop</h2></section>'));
});

it('does not count the items of a list, nor the items of a list inside them', function (): void {
    $card = static fn (string $title, int $players): string => '<div class="kaart"><h3>'.$title.'</h3><div class="spelers">'.str_repeat('<span>Naam</span>', $players).'</div></div>';

    expect(fingerprintOf('<section><h2>Teams</h2><div class="grid">'.$card('Een', 2).$card('Twee', 4).'</div></section>'))
        ->toBe(fingerprintOf('<section><h2>Teams</h2><div class="grid">'.$card('Een', 3).$card('Twee', 1).$card('Drie', 5).'</div></section>'));
});

it('does not count empty decoration that repeats', function (): void {
    expect(fingerprintOf('<section><h2>Kop</h2><div class="schaal">'.str_repeat('<div class="tik"></div>', 13).'</div></section>'))
        ->toBe(fingerprintOf('<section><h2>Kop</h2><div class="schaal">'.str_repeat('<div class="tik"></div>', 7).'</div></section>'));
});

it('differs when the items of a list are built differently', function (): void {
    expect(fingerprintOf('<section><div class="grid"><div><h3>A</h3><p>a</p></div><div><h3>B</h3><p>b</p></div></div></section>'))
        ->not->toBe(fingerprintOf('<section><div class="grid"><div><h3>A</h3><a href="/a">a</a></div><div><h3>B</h3><a href="/b">b</a></div></div></section>'));
});

it('does not count the markup inside running text', function (): void {
    expect(fingerprintOf('<section><h2>Over <em>ons</em></h2><p>Lees <a href="/meer">meer</a>.</p></section>'))
        ->toBe(fingerprintOf('<section><h2>Over ons</h2><p>Lees meer.</p></section>'));
});
