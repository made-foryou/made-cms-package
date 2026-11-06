<?php

declare(strict_types=1);

use Dom\Element;
use Made\CmsPackage\Content\Scan\Html;
use Made\CmsPackage\Content\Scan\StripFinder;

/**
 * The strips the finder takes from a page with the given body, each as "tag#id" or "tag.class".
 *
 * @return array{title: string, strips: list<string>}
 */
function stripsOf(string $body, string $title = 'Testpagina'): array
{
    $found = (new StripFinder)->find(Html::parse(scanDocument($body, $title)));

    return [
        'title' => $found['title'],
        'strips' => array_map(
            static fn (Element $strip): string => $strip->localName.($strip->id !== '' ? '#'.$strip->id : '').($strip->className !== '' ? '.'.$strip->className : ''),
            $found['strips'],
        ),
    ];
}

it('takes the children of main as strips, in order', function (): void {
    expect(stripsOf('<main><section id="a"><p>A</p></section><div class="b"><p>B</p></div><article><p>C</p></article></main>')['strips'])
        ->toBe(['section#a', 'div.b', 'article']);
});

it('leaves the header, the navigation and the footer of the site out', function (): void {
    $body = '<header><p>Logo</p></header><nav><a href="/">Home</a></nav>'
        .'<main><section id="a"><p>A</p></section></main>'
        .'<footer><p>© 2026</p></footer>';

    expect(stripsOf($body)['strips'])->toBe(['section#a']);
});

it('finds the strips without main by going down through wrappers, past what the site puts around them', function (): void {
    $body = '<div id="app"><div class="page">'
        .'<nav><a href="/">Home</a></nav>'
        .'<section id="a"><h2>A</h2></section>'
        .'<section id="b"><h2>B</h2></section>'
        .'<button><svg viewBox="0 0 1 1"><path d="M0 0"></path></svg></button>'
        .'<footer><p>© 2026</p></footer>'
        .'</div></div>';

    expect(stripsOf($body)['strips'])->toBe(['section#a', 'section#b']);
});

it('goes down through a wrapper in main', function (): void {
    expect(stripsOf('<main><div class="container"><section id="a"><p>A</p></section><section id="b"><p>B</p></section></div></main>')['strips'])
        ->toBe(['section#a', 'section#b']);
});

it('takes the sections out of a wrapper that holds only sections', function (): void {
    expect(stripsOf('<main><div class="wrap"><section id="a"><p>A</p></section><section id="b"><p>B</p></section></div><section id="c"><p>C</p></section></main>')['strips'])
        ->toBe(['section#a', 'section#b', 'section#c']);
});

it('leaves out what is hidden, has no content or belongs to the CMS', function (): void {
    $body = '<main>'
        .'<script>var a = 1;</script><template><section><p>T</p></section></template>'
        .'<section hidden><p>Verborgen</p></section><div aria-hidden="true"><p>Versiering</p></div>'
        .'<div class="leeg"><span></span></div>'
        .'<made-cms-launcher><p>Beheer</p></made-cms-launcher>'
        .'<section id="a"><p>A</p></section>'
        .'</main>';

    expect(stripsOf($body)['strips'])->toBe(['section#a']);
});

it('takes the heading in main as the title of the page, not as a strip', function (): void {
    expect(stripsOf('<main><h1>Over ons</h1><section id="a"><p>A</p></section></main>', 'Over ons · Site'))
        ->toBe(['title' => 'Over ons', 'strips' => ['section#a']]);
});

it('takes the title of the document when main has no heading of its own', function (): void {
    expect(stripsOf('<main><section id="a"><h1>Welkom</h1></section></main>', 'Welkom - Site')['title'])->toBe('Welkom - Site');
});

it('takes main as one strip when it only holds text', function (): void {
    expect(stripsOf('<main><h1>Privacy</h1><p>Wij bewaren niets.</p><h2>Cookies</h2><p>Geen.</p></main>'))
        ->toBe(['title' => 'Privacy', 'strips' => ['main']]);
});

it('finds no strips on an empty page', function (): void {
    expect(stripsOf('')['strips'])->toBe([])
        ->and((new StripFinder)->find(Html::parse(''))['strips'])->toBe([]);
});
