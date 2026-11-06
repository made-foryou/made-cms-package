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
        ->not->toBe(fingerprintOf('<section><h3>Kop</h3><p>Tekst</p></section>'));
});
