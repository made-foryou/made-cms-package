<?php

declare(strict_types=1);

use Dom\Element;
use Illuminate\Support\Facades\Validator;
use Made\CmsPackage\Content\Actions\CombinePageScans;
use Made\CmsPackage\Content\Actions\RecognisePage;
use Made\CmsPackage\Content\Data\ScannedPage;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Data\ScanType;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Scan\Html;

/*
| The scan of the workbench site, whose strip types and views are known: the
| hero and the cards. The pages are rendered by the workbench and scanned
| without their data-cms attributes, as a site looks before it is coupled.
*/

beforeEach(function (): void {
    useWorkbenchSite();

    $card = static fn (string $title, string $url): array => ['titel' => $title, 'tekst' => "Regel een\nRegel twee", 'link' => ['url' => $url]];

    Page::factory()->published()->at('/')->withStrips([
        storedStrip('hero', [
            'label' => 'Workbench',
            'kop' => 'Welkom',
            'intro' => '<p>Twee <strong>zinnen</strong>.</p><p>En nog een.</p>',
            'knoppen' => [
                ['knop' => ['text' => 'Over ons', 'link' => ['url' => '/over-ons']]],
                ['knop' => ['text' => 'Lid worden', 'link' => ['url' => '/lid-worden']]],
            ],
        ], id: 'hero'),
        storedStrip('cards', [
            'kop' => 'Kaarten',
            'kolommen' => '3',
            'kaarten' => [$card('Spelen', '/spelen'), $card('Trainen', '/trainen'), $card('Teams', '/teams')],
        ], id: 'cards'),
    ])->create(['title' => 'Home']);

    Page::factory()->published()->at('/over-ons')->withStrips([
        storedStrip('cards', ['kop' => 'Meer', 'kaarten' => [$card('Bestuur', '/bestuur'), $card('Leden', '/leden')]], id: 'meer'),
    ])->create(['title' => 'Over ons']);
});

/**
 * The page as the workbench renders it, with or without its data-cms attributes.
 */
function workbenchHtml(string $url, bool $coupled = false): string
{
    $html = (string) test()->get($url)->assertOk()->getContent();

    return $coupled ? $html : (string) preg_replace('/\sdata-cms-[a-z-]+="[^"]*"/', '', $html);
}

function workbenchScan(): ScanResult
{
    return CombinePageScans::run([
        RecognisePage::run(new ScannedPage('/', workbenchHtml('/'))),
        RecognisePage::run(new ScannedPage('/over-ons', workbenchHtml('/over-ons'))),
    ]);
}

it('scans a page without the attributes of the coupling', function (): void {
    expect(workbenchHtml('/'))->not->toContain('data-cms-')
        ->and(workbenchHtml('/', coupled: true))->toContain('data-cms-strip="hero"');
});

it('proposes the hero and the cards, with the cards on both pages as one type', function (): void {
    $scan = workbenchScan();

    expect(array_map(static fn (ScanType $type): array => [$type->key, $type->count], $scan->types))->toBe([['strook-1', 1], ['strook-2', 2]])
        ->and(array_map(static fn ($page): array => [$page->url, $page->title, array_column($page->toArray()['strips'], 'type')], $scan->pages))->toBe([
            ['/', 'Home', ['strook-1', 'strook-2']],
            ['/over-ons', 'Over ons', ['strook-2']],
        ]);
});

it('proposes the fields of the workbench strip types', function (): void {
    // Hero: label is a Text in a <p><small>, which reads as a paragraph. Cards: kolommen is a choice that only
    // the data-kolommen attribute shows, so no field holds it.
    expect(array_map(outline(...), workbenchScan()->types))->toBe([
        ['tekst: long_text', 'kop: text', 'inhoud: rich_text', ['items: items' => ['knop: button']]],
        ['kop: text', ['items: items' => ['kop: text', 'tekst: long_text', 'link: link']]],
    ])
        ->and([workbenchScan()->types[1]->fields[1]->min, workbenchScan()->types[1]->fields[1]->max])->toBe([1, 3]);
});

it('reads the values that the editor stored', function (): void {
    $strips = workbenchScan()->pages[0]->strips;

    expect($strips[0]->values)->toBe([
        'tekst' => 'Workbench',
        'kop' => 'Welkom',
        'inhoud' => '<p>Twee <strong>zinnen</strong>.</p><p>En nog een.</p>',
        'items' => [
            ['knop' => ['text' => 'Over ons', 'link' => ['url' => '/over-ons']]],
            ['knop' => ['text' => 'Lid worden', 'link' => ['url' => '/lid-worden']]],
        ],
    ])
        ->and($strips[1]->values['items'][2])->toBe(['kop' => 'Teams', 'tekst' => "Regel een\nRegel twee", 'link' => ['url' => '/teams']]);
});

it('points at the strips and the fields that the views mark', function (): void {
    $scan = workbenchScan();
    $document = Html::parse(workbenchHtml('/', coupled: true));
    $marked = [];

    foreach ($scan->pages[0]->strips as $strip) {
        $root = $document->querySelector($strip->selector);

        expect($root?->getAttribute('data-cms-strip'))->toBeIn(['hero', 'cards']);

        foreach (($scan->type($strip->type)->fields ?? []) as $field) {
            $element = $document->querySelector($strip->selector.' > '.$field->selector);
            $only = $element?->firstElementChild;
            $marked[$strip->type][$field->key] = $element?->getAttribute('data-cms-field')
                ?? $element?->getAttribute('data-cms-items')
                ?? ($only instanceof Element && $element->childElementCount === 1 ? $only->getAttribute('data-cms-field') : null);
        }
    }

    expect($marked)->toBe([
        'strook-1' => ['tekst' => 'label', 'kop' => 'kop', 'inhoud' => 'intro', 'items' => 'knoppen'],
        'strook-2' => ['kop' => 'kop', 'items' => 'kaarten'],
    ]);
});

it('proposes valid strip types that accept what it read', function (): void {
    $scan = workbenchScan();
    $types = scanStripTypes($scan);

    foreach ($scan->pages as $page) {
        foreach ($page->strips as $strip) {
            $type = $types->find($strip->type);

            expect(Validator::make($strip->values, $type->rules(), [], $type->attributes())->passes())->toBeTrue();
        }
    }
});

it('keeps its result', function (): void {
    expect(scanJson(workbenchScan()))->toMatchSnapshot();
});
