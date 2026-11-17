<?php

declare(strict_types=1);

/**
 * The component as the site writes it, in an element of its own.
 *
 * @param  array<string, mixed>  $data
 */
function emphasisHtml(string $template, array $data = []): string
{
    return trim((string) test()->blade($template, $data));
}

it('prints a text with the words between asterisks as an <em>, inside the element of the site', function (string $text, string $html): void {
    expect(emphasisHtml('<h2 class="h-l" data-cms-field="kop"><x-made-cms::emphasis :text="$kop" /></h2>', ['kop' => $text]))
        ->toBe('<h2 class="h-l" data-cms-field="kop">'.$html.'</h2>');
})->with([
    'one word' => ['Jullie dag in *vertrouwde* handen', 'Jullie dag in <em>vertrouwde</em> handen'],
    'more than one piece' => ['*Samen* spelen, *samen* winnen', '<em>Samen</em> spelen, <em>samen</em> winnen'],
    'inside a word' => ['Privacy*verklaring*', 'Privacy<em>verklaring</em>'],
    'an asterisk without a partner' => ['Sterren * en 2 * 3', 'Sterren * en 2 * 3'],
    'no emphasis' => ['Welkom', 'Welkom'],
]);

it('escapes the text inside and outside the emphasis', function (): void {
    expect(emphasisHtml('<p><x-made-cms::emphasis :text="$kop" /></p>', ['kop' => '<b>Hoi</b> *<i>daar</i>* & "co"']))
        ->toBe('<p>&lt;b&gt;Hoi&lt;/b&gt; <em>&lt;i&gt;daar&lt;/i&gt;</em> &amp; &quot;co&quot;</p>');
});

it('prints nothing without a text', function (?string $text): void {
    expect(emphasisHtml('<h2><x-made-cms::emphasis :text="$kop" /></h2>', ['kop' => $text]))->toBe('<h2></h2>');
})->with([
    'nothing' => [null],
    'an empty text' => [''],
]);

it('prints the text of every item of a list', function (): void {
    $columns = [['kop' => 'Een *mooie* dag'], ['kop' => 'Geen zorgen']];

    expect(emphasisHtml('<div data-cms-items="kolommen">@foreach ($kolommen as $kolom)<h3 data-cms-field="kop"><x-made-cms::emphasis :text="$kolom[\'kop\']" /></h3>@endforeach</div>', ['kolommen' => $columns]))
        ->toBe('<div data-cms-items="kolommen"><h3 data-cms-field="kop">Een <em>mooie</em> dag</h3><h3 data-cms-field="kop">Geen zorgen</h3></div>');
});
