<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Fields\RichText;

it('validates the html as a long string', function (): void {
    expect(RichText::make('tekst')->required()->rules('tekst'))
        ->toBe(['tekst' => ['required', 'string', 'max:65535']]);
});

it('keeps the allowed markup', function (): void {
    $html = '<p>Bij <strong>TTV</strong> draait het om <em>sport</em>.</p>'
        .'<ul><li>Een</li></ul><ol><li>Twee</li></ol>'
        .'<p><a href="https://example.com">extern</a> <a href="/lid-worden">intern</a> '
        .'<a href="mailto:info@example.com">mail</a> <a href="tel:0612345678">bel</a></p>';

    expect(RichText::make('tekst')->normalize($html))
        ->toBe(str_replace('info@example.com', 'info&#64;example.com', $html));
});

it('keeps the text of markup that is not allowed', function (): void {
    expect(RichText::make('tekst')->normalize('<h1>Kop</h1><p><span class="x">Tekst</span> <b>vet</b></p>'))
        ->toBe('Kop<p>Tekst vet</p>');
});

it('removes scripts, styles and embeds with their content', function (): void {
    expect(RichText::make('tekst')->normalize(
        '<p>Hallo</p><script>alert(1)</script><style>p{}</style><title>T</title>'
        .'<iframe src="https://example.com">x</iframe><noscript>n</noscript><my-widget>w</my-widget>',
    ))->toBe('<p>Hallo</p>');
});

it('removes attributes and unsafe links', function (): void {
    expect(RichText::make('tekst')->normalize('<p class="x" onclick="evil()">Hallo</p><a href="javascript:alert(1)" target="_blank">klik</a>'))
        ->toBe('<p>Hallo</p><a>klik</a>');
});

it('keeps long texts whole', function (): void {
    $html = '<p>'.str_repeat('a', 30000).'</p>';

    expect(RichText::make('tekst')->normalize($html))->toBe($html);
});

it('leaves a value that is not a string alone', function (): void {
    expect(RichText::make('tekst')->normalize(null))->toBeNull();
});
