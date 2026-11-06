<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('answers the strips of the working copy in their stored order with their counts', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('cards', id: 'a')])
        ->withDraft([storedStrip('hero', ['kop' => 'Welkom bij de club'], id: 'b'), storedStrip('oud', id: 'c'), storedStrip('cards', visible: false, id: 'a')])
        ->create();

    $response = $this->getJson('made-cms/pages/'.$page->id.'/strips')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('counts', '3 stroken, 1 verborgen, 1 niet op de site')
        ->assertJsonPath('strips.0.summary', 'Welkom bij de club')
        ->assertJsonPath('strips.1.known', false);

    expect(array_column($response->json('strips'), 'id'))->toBe(['b', 'c', 'a']);
});

it('answers 401 to a guest', function (): void {
    auth('made-cms')->logout();
    $page = Page::factory()->create();

    $this->getJson('made-cms/pages/'.$page->id.'/strips')->assertUnauthorized();
});
