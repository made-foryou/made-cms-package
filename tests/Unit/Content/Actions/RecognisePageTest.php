<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\RecognisePage;
use Made\CmsPackage\Content\Data\ScannedPage;
use Made\CmsPackage\Content\Scan\Html;

it('reads the address, the title and the strips of a page, each where it is in the document', function (): void {
    $html = scanDocument('<main><h1>Over ons</h1><section id="intro"><h2>Wie</h2></section><section class="cta"><h2>Doe mee</h2></section></main>');

    $page = RecognisePage::run(new ScannedPage('/over-ons', $html));
    $document = Html::parse($html);

    expect($page->url)->toBe('/over-ons')
        ->and($page->title)->toBe('Over ons')
        ->and(array_map(static fn ($strip): ?string => $strip->hint, $page->strips))->toBe(['#intro', '.cta'])
        ->and(array_map(static fn ($strip): string => $strip->selector, $page->strips))->toBe(['#intro', 'body > main > section:nth-of-type(2)'])
        ->and($document->querySelector($page->strips[1]->selector)?->className)->toBe('cta');
});

it('gives strips with the same structure the same fingerprint', function (): void {
    $page = recognise('<main><section><h2>Een</h2><p>Tekst</p></section><section><h2>Twee</h2><p>Andere tekst</p></section><section><h2>Drie</h2></section></main>');

    expect($page->strips[0]->fingerprint)->toBe($page->strips[1]->fingerprint)
        ->and($page->strips[2]->fingerprint)->not->toBe($page->strips[0]->fingerprint);
});

it('hints at a type by a class only when the class reads as a name', function (string $class, ?string $hint): void {
    expect(recognise('<main><section class="'.$class.'"><h2>Kop</h2></section></main>')->strips[0]->hint)->toBe($hint);
})->with([
    ['hero relative', '.hero'],
    ['relative h-[80vh] overflow-hidden bg-[#0a1628]', null],
    ['bg-white py-24 lg:py-32', null],
    ['container mx-auto', null],
    ['c-teaser--wide js-reveal', '.c-teaser--wide'],
]);

it('reads a broken document without failing', function (): void {
    expect(RecognisePage::run(new ScannedPage('/', '<main><section><h2>Open'))->strips)->toHaveCount(1)
        ->and(RecognisePage::run(new ScannedPage('/', ''))->strips)->toBe([]);
});
