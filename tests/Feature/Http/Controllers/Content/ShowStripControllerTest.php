<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
});

it('answers at an address under the pages of the cms', function (): void {
    $page = Page::factory()->create();

    expect(route('made-cms-package.pages.strips.show', ['page' => $page, 'strip' => 'a'], false))
        ->toBe('/made-cms/pages/'.$page->id.'/strips/a');
});

it('hands the side panel the fields and the draft values of a strip', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['kop' => 'Live'], id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Concept'], id: 'a')])
        ->create();

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/a')
        ->assertOk()
        ->assertJsonPath('strip.id', 'a')
        ->assertJsonPath('strip.type', 'hero')
        ->assertJsonPath('strip.label', 'Hero')
        ->assertJsonPath('strip.visible', true)
        ->assertJsonPath('strip.values.kop', 'Concept')
        ->assertJsonPath('strip.fields.1.key', 'kop')
        ->assertJsonPath('strip.fields.1.required', true)
        ->assertJsonPath('strip.fields.5.type', 'items')
        ->assertJsonPath('strip.fields.5.fields.2.options.0', ['value' => 'primary', 'label' => 'Primair']);

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('offers every page of the site as a link, in the order of the menu', function (): void {
    $home = Page::factory()->published()->atPosition(0)->at('/')->create(['title' => 'Home']);
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons']);
    Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);
    $page = Page::factory()->atPosition(2)->withDraft([storedStrip('hero', id: 'a')])->create(['title' => 'Nieuw']);

    $pages = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/a')
        ->json('pages');

    expect($pages)->toContain(['id' => $home->id, 'title' => 'Home', 'path' => '/', 'status' => 'published'])
        ->and(array_column($pages, 'title'))->toBe(['Home', 'Over ons', 'Team', 'Nieuw'])
        ->and($pages[1]['status'])->toBe('draft');
});

it('answers 404 for a strip that is not on the page', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/b')
        ->assertNotFound();
});

it('answers 404 for a strip of a type that is no longer in the catalogue', function (): void {
    $page = Page::factory()->withDraft([storedStrip('oud', ['x' => 1], id: 'a')])->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/'.$page->id.'/strips/a')
        ->assertNotFound();
});

it('answers 404 for a page that does not exist', function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->getJson('made-cms/pages/999/strips/a')
        ->assertNotFound();
});

it('answers 401 to a guest', function (): void {
    $page = Page::factory()->withDraft([storedStrip('hero', id: 'a')])->create();

    $this->getJson('made-cms/pages/'.$page->id.'/strips/a')->assertUnauthorized();
});
