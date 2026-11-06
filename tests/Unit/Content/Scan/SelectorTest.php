<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Made\CmsPackage\Content\Scan\Html;
use Made\CmsPackage\Content\Scan\Selector;

function selectorDocument(): HTMLDocument
{
    return Html::parse(scanDocument(
        '<div id="app"><section><div><h2>Eerste</h2></div><p>Een</p><div><h2>Tweede</h2><span>a</span><span>b</span></div></section>'
        .'<section id="over-ons"><p>Over</p></section><section id="dubbel"><p>1</p></section><section id="dubbel"><p>2</p></section></div>',
    ));
}

it('steps by tag, counting from the second element of a tag', function (): void {
    $document = selectorDocument();
    $section = $document->querySelector('#app > section');
    $span = $document->querySelectorAll('#app > section > div')[1]->querySelectorAll('span')[1];

    expect(Selector::relative($span, $section))->toBe('div:nth-of-type(2) > span:nth-of-type(2)')
        ->and(Selector::relative($section, $section))->toBe('');
});

it('finds the element back from its strip in the first match', function (): void {
    $document = selectorDocument();
    $section = $document->querySelector('#app > section');

    foreach ($section->querySelectorAll('*') as $element) {
        $path = Selector::absolute($section).' > '.Selector::relative($element, $section);

        expect($document->querySelector($path))->toBe($element);
    }
});

it('places a strip by its id when the id is its own, by its path otherwise', function (): void {
    $document = selectorDocument();
    $sections = $document->querySelectorAll('#app > section');

    expect(Selector::absolute($sections[0]))->toBe('body > div > section')
        ->and(Selector::absolute($sections[1]))->toBe('#over-ons')
        ->and(Selector::absolute($sections[2]))->toBe('body > div > section:nth-of-type(3)')
        ->and($document->querySelector(Selector::absolute($sections[3])))->toBe($sections[3]);
});
