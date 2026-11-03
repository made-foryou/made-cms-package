<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Exceptions\MissingStripView;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\Fields\Text;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Http\Components\Strips;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;

beforeEach(function (): void {
    useStripTypes();

    app('view')->addLocation(__DIR__.'/../../../Fixtures/views');
});

it('renders every strip with the view of the convention', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['kop' => 'Welkom'], id: 'a')])->create();

    $this->blade('<x-made-cms::strips :page="$page" />', ['page' => $page])
        ->assertSee('<section data-cms-strip="a">', false)
        ->assertSee('<h2 data-cms-field="kop">Welkom</h2>', false);
});

it('renders a strip with the view its type names', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('cards', ['kaarten' => [['titel' => 'Een'], ['titel' => 'Twee']]], id: 'b')])->create();

    $this->blade('<x-made-cms::strips :page="$page" />', ['page' => $page])
        ->assertSee('data-cms-strip="b"', false)
        ->assertSeeText('2 kaarten');
});

it('renders the strips in the order of the page and leaves hidden ones out', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['kop' => 'Eerste'], id: 'a'),
        storedStrip('hero', ['kop' => 'Verborgen'], visible: false, id: 'b'),
        storedStrip('cards', id: 'c'),
    ])->create();

    $this->blade('<x-made-cms::strips :page="$page" />', ['page' => $page])
        ->assertSeeInOrder(['data-cms-strip="a"', 'data-cms-strip="c"'], false)
        ->assertDontSee('data-cms-strip="b"', false)
        ->assertDontSeeText('Verborgen');
});

it('renders nothing for a page without strips', function (): void {
    $page = Page::factory()->published()->create();

    expect(trim((string) $this->blade('<x-made-cms::strips :page="$page" />', ['page' => $page])))->toBe('');
});

it('escapes text and prints rich text as its cleaned html', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['kop' => '<b>Hoi</b>', 'tekst' => '<p>Wij <strong>spelen</strong>.</p>'], id: 'a')])->create();

    $this->blade('<x-made-cms::strips :page="$page" />', ['page' => $page])
        ->assertSee('<h2 data-cms-field="kop">&lt;b&gt;Hoi&lt;/b&gt;</h2>', false)
        ->assertSee('<div data-cms-field="tekst"><p>Wij <strong>spelen</strong>.</p></div>', false);
});

it('hands the view the rendered values: links as urls, buttons as text and url', function (): void {
    $target = Page::factory()->published()->at('/over-ons')->create();
    $page = Page::factory()->published()->withStrips([storedStrip('hero', [
        'actie' => ['text' => 'Lid worden', 'link' => ['url' => '/lid-worden']],
        'knoppen' => [['tekst' => 'Over ons', 'gaat_naar' => ['page' => $target->id]]],
    ], id: 'a')])->create();

    $this->blade('<x-made-cms::strips :page="$page" />', ['page' => $page])
        ->assertSee('<a class="actie" href="/lid-worden">Lid worden</a>', false)
        ->assertSee('<a class="knop" href="/over-ons">Over ons</a>', false);
});

it('names the strip type and the view it looked for when the view is missing', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new InlineStripType('quote', [Text::make('citaat')])]));
    $page = Page::factory()->published()->withStrips([storedStrip('quote', ['citaat' => 'Hoi'], id: 'a')])->create();

    expect(fn (): mixed => Strips::resolve(['page' => $page])->render())
        ->toThrow(MissingStripView::class, 'Strip type [quote] ('.InlineStripType::class.') has no view [strips.quote]');
});

it('renders no strip at all when one of them has no view', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new InlineStripType('quote', [Text::make('citaat')])]));
    $page = Page::factory()->published()->withStrips([storedStrip('quote', id: 'a')])->create();

    expect(fn (): mixed => $this->blade('<x-made-cms::strips :page="$page" />', ['page' => $page]))
        ->toThrow(Exception::class, 'has no view [strips.quote]');
});
