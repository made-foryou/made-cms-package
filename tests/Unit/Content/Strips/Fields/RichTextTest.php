<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Strips\Fields\RichText;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;

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

it('renders a stored value cleaned, also one that never passed normalize()', function (string $stored, string $rendered): void {
    expect(RichText::make('tekst')->render($stored))->toBe($rendered);
})->with([
    'a script' => ['<p>Hallo</p><script>alert(1)</script>', '<p>Hallo</p>'],
    'event attributes' => ['<p onerror="alert(1)">Hallo</p><img src="x" onerror="alert(1)">', '<p>Hallo</p>'],
    'a javascript link' => ['<a href="javascript:alert(1)">klik</a>', '<a>klik</a>'],
    'the plain text of a field that used to be a text' => ['1 < 2 & 3 > 2', '1 &lt; 2 &amp; 3 &gt; 2'],
]);

it('renders a cleaned value as it is stored', function (): void {
    $field = RichText::make('tekst');
    $stored = $field->normalize('<p>Bij <strong>TTV</strong> draait het om <em>sport</em>.</p>'
        .'<ul><li>Een</li></ul><ol><li>Twee</li></ol>'
        .'<p><a href="https://example.com">extern</a> <a href="/lid-worden">intern</a> '
        .'<a href="mailto:info@example.com">mail</a> <a href="tel:0612345678">bel</a></p>');

    expect($field->render($stored))->toBe($stored);
});

it('renders a default cleaned', function (): void {
    $type = new InlineStripType('intro', [RichText::make('tekst')->default('<p>Welkom</p><img src="x" onerror="alert(1)">')]);

    expect($type->render([]))->toBe(['tekst' => '<p>Welkom</p>']);
});

it('renders a value that is not a string as nothing', function (mixed $stored): void {
    expect(RichText::make('tekst')->render($stored))->toBeNull();
})->with([
    'nothing' => [null],
    'a list' => [['<img src="x" onerror="alert(1)">']],
]);

it('keeps a long text whole when cleaning made it longer than the limit of the input', function (): void {
    $field = RichText::make('tekst');
    $stored = $field->normalize('<p>'.str_repeat("auto's ", 6400).'</p>');

    expect(strlen((string) $stored))->toBeGreaterThan(RichText::MAX_LENGTH)
        ->and($field->render($stored))->toBe($stored);
});
