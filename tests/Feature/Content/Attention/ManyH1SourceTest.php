<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Attention\ManyH1Source;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;

beforeEach(function (): void {
    app()->instance(StripTypes::class, new StripTypes([
        new InlineStripType('hero', [Heading::make('kop')->tag('h1')], 'Hero'),
        new InlineStripType('tekst-foto', [Heading::make('kop'), Text::make('tekst')], 'Tekst met foto'),
        new InlineStripType('kaarten', [Heading::make('kop'), Items::make('kaarten', [Heading::make('titel')->tag('h3')])], 'Kaarten'),
        new InlineStripType('intro', [Heading::make('kop')->tag('h1')->default('Welkom')], 'Intro'),
    ]));
});

/**
 * @return array{text: string, tag: string}
 */
function mainHeading(string $text): array
{
    return ['text' => $text, 'tag' => 'h1'];
}

it('asks for one h1 when the strips a visitor sees hold more, and names their strips', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['kop' => 'Welkom bij TTV Merwestad']),
        storedStrip('tekst-foto', ['kop' => mainHeading('Over de club')]),
    ])->create();

    expect((new ManyH1Source)->items($page, new AttentionContext))->toEqual([
        new AttentionItem(AttentionReason::ManyH1, AttentionSeverity::Medium, ['count' => 2], ['Hero', 'Tekst met foto']),
    ]);
});

it('leaves a page alone with one h1 a visitor sees', function (array $strips): void {
    $page = Page::factory()->published()->withStrips($strips)->create();

    expect((new ManyH1Source)->items($page, new AttentionContext))->toBe([]);
})->with([
    'one h1' => [[storedStrip('hero', ['kop' => 'Welkom']), storedStrip('tekst-foto', ['kop' => 'Over de club'])]],
    'a second one in a hidden strip' => [[storedStrip('hero', ['kop' => 'Welkom']), storedStrip('tekst-foto', ['kop' => mainHeading('Over de club')], visible: false)]],
    'a second one of a type that left the catalogue' => [[storedStrip('hero', ['kop' => 'Welkom']), storedStrip('weg', ['kop' => mainHeading('Oud')])]],
    'a second one without a text' => [[storedStrip('hero', ['kop' => 'Welkom']), storedStrip('tekst-foto', ['kop' => ['text' => null, 'tag' => 'h1']])]],
    'no heading at all' => [[storedStrip('tekst-foto', ['kop' => ['text' => 'Over de club', 'tag' => 'span']])]],
]);

it('counts only the published strips, never the draft', function (): void {
    $published = [storedStrip('hero', ['kop' => 'Welkom'])];
    $page = Page::factory()->published()->withStrips($published)
        ->withDraft([...$published, storedStrip('tekst-foto', ['kop' => mainHeading('Over de club')])])
        ->create();

    expect((new ManyH1Source)->items($page, new AttentionContext))->toBe([]);
});

it('counts an h1 in a list, and names a strip once however many it holds', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('kaarten', ['kaarten' => [['id' => 'a', 'titel' => mainHeading('Jeugd')], ['id' => 'b', 'titel' => 'Senioren'], ['id' => 'c', 'titel' => mainHeading('Recreanten')]]]),
    ])->create();

    expect((new ManyH1Source)->items($page, new AttentionContext))->toEqual([
        new AttentionItem(AttentionReason::ManyH1, AttentionSeverity::Medium, ['count' => 2], ['Kaarten']),
    ]);
});

it('counts the h1 of a heading the strip renders from its default', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('intro'), storedStrip('hero', ['kop' => 'Welkom'])])->create();

    expect((new ManyH1Source)->items($page, new AttentionContext))->toEqual([
        new AttentionItem(AttentionReason::ManyH1, AttentionSeverity::Medium, ['count' => 2], ['Intro', 'Hero']),
    ]);
});

it('names every type once, in the order of the page', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['kop' => 'Welkom']),
        storedStrip('tekst-foto', ['kop' => mainHeading('Over de club')]),
        storedStrip('hero', ['kop' => 'Lid worden']),
    ])->create();

    expect((new ManyH1Source)->items($page, new AttentionContext))->toEqual([
        new AttentionItem(AttentionReason::ManyH1, AttentionSeverity::Medium, ['count' => 3], ['Hero', 'Tekst met foto']),
    ]);
});

it('leaves a page alone that Google does not show', function (Closure $page): void {
    expect((new ManyH1Source)->items($page(), new AttentionContext))->toBe([]);
})->with([
    'never published' => [static fn (): Page => Page::factory()->withStrips([storedStrip('hero', ['kop' => 'Een']), storedStrip('hero', ['kop' => 'Twee'])])->create()],
    'noindex' => [static fn (): Page => Page::factory()->published()->withStrips([storedStrip('hero', ['kop' => 'Een']), storedStrip('hero', ['kop' => 'Twee'])])->create(['indexable' => false])],
    'hidden' => [static fn (): Page => Page::factory()->published()->hidden()->withStrips([storedStrip('hero', ['kop' => 'Een']), storedStrip('hero', ['kop' => 'Twee'])])->create()],
]);
