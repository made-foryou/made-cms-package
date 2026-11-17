<?php

declare(strict_types=1);

/**
 * The component as the site writes it, rendered without the whitespace around it.
 *
 * @param  array<string, mixed>  $data
 */
function headingHtml(string $template, array $data = []): string
{
    return trim((string) test()->blade($template, $data));
}

it('renders a heading as the element of its level with the field it shows', function (): void {
    expect(headingHtml('<x-made-cms::heading :heading="$kop" field="kop" class="hero__titel" />', ['kop' => ['text' => 'Welkom', 'tag' => 'h2']]))
        ->toBe('<h2 data-cms-field="kop" class="hero__titel">Welkom</h2>');
});

it('renders every level, also a text that is no heading for search engines', function (string $tag): void {
    expect(headingHtml('<x-made-cms::heading :heading="$kop" field="kop" />', ['kop' => ['text' => 'Welkom', 'tag' => $tag]]))
        ->toBe("<{$tag} data-cms-field=\"kop\">Welkom</{$tag}>");
})->with(['h1', 'h3', 'h6', 'span']);

it('passes the other attributes on', function (): void {
    expect(headingHtml('<x-made-cms::heading :heading="$kop" field="kop" id="welkom" class="titel" aria-describedby="intro" />', ['kop' => ['text' => 'Welkom', 'tag' => 'h1']]))
        ->toBe('<h1 data-cms-field="kop" id="welkom" class="titel" aria-describedby="intro">Welkom</h1>');
});

it('escapes the text', function (): void {
    expect(headingHtml('<x-made-cms::heading :heading="$kop" field="kop" />', ['kop' => ['text' => '<b>Hoi</b> & {{ $x }}', 'tag' => 'h2']]))
        ->toBe('<h2 data-cms-field="kop">&lt;b&gt;Hoi&lt;/b&gt; &amp; {{ $x }}</h2>');
});

it('renders nothing for a heading without a text', function (mixed $value): void {
    expect(headingHtml('<x-made-cms::heading :heading="$kop" field="kop" class="titel" />', ['kop' => $value]))->toBe('');
})->with([
    'nothing' => [null],
    'an empty text' => [['text' => '', 'tag' => 'h2']],
    'no text' => [['tag' => 'h2']],
]);

it('renders a level that is no heading tag as a span, so a value from elsewhere never prints markup', function (mixed $tag): void {
    expect(headingHtml('<x-made-cms::heading :heading="$kop" field="kop" />', ['kop' => ['text' => 'Welkom', 'tag' => $tag]]))
        ->toBe('<span data-cms-field="kop">Welkom</span>');
})->with([
    'another element' => ['script'],
    'markup' => ['h2 onclick="alert(1)"'],
    'no level' => [null],
]);

it('leaves the field out when the site does not name one', function (): void {
    expect(headingHtml('<x-made-cms::heading :heading="$kop" class="titel" />', ['kop' => ['text' => 'Welkom', 'tag' => 'h2']]))
        ->toBe('<h2 class="titel">Welkom</h2>');
});

it('names a field of an item as the item names it', function (): void {
    $cards = [['titel' => ['text' => 'Teams', 'tag' => 'h3']], ['titel' => ['text' => 'Agenda', 'tag' => 'h3']]];

    expect(headingHtml('<ul data-cms-items="kaarten">@foreach ($kaarten as $kaart)<li><x-made-cms::heading :heading="$kaart[\'titel\']" field="titel" /></li>@endforeach</ul>', ['kaarten' => $cards]))
        ->toBe('<ul data-cms-items="kaarten"><li><h3 data-cms-field="titel">Teams</h3></li><li><h3 data-cms-field="titel">Agenda</h3></li></ul>');
});

it('prints the words between asterisks as an <em> when the view says the heading has emphasis', function (): void {
    expect(headingHtml('<x-made-cms::heading :heading="$kop" field="kop" emphasis class="hero__kop" />', ['kop' => ['text' => 'Jullie dag in *vertrouwde* handen', 'tag' => 'h1']]))
        ->toBe('<h1 data-cms-field="kop" class="hero__kop">Jullie dag in <em>vertrouwde</em> handen</h1>');
});

it('prints the asterisks as text without emphasis', function (string $template): void {
    expect(headingHtml($template, ['kop' => ['text' => 'Jullie dag in *vertrouwde* handen', 'tag' => 'h2']]))
        ->toBe('<h2 data-cms-field="kop">Jullie dag in *vertrouwde* handen</h2>');
})->with([
    'no attribute' => ['<x-made-cms::heading :heading="$kop" field="kop" />'],
    'switched off' => ['<x-made-cms::heading :heading="$kop" field="kop" :emphasis="false" />'],
]);

it('escapes the text inside and outside the emphasis', function (): void {
    expect(headingHtml('<x-made-cms::heading :heading="$kop" field="kop" emphasis />', ['kop' => ['text' => '<b>Hoi</b> *<script>x</script>* & {{ $x }}', 'tag' => 'h2']]))
        ->toBe('<h2 data-cms-field="kop">&lt;b&gt;Hoi&lt;/b&gt; <em>&lt;script&gt;x&lt;/script&gt;</em> &amp; {{ $x }}</h2>');
});
