<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('answers the changes of the draft with the texts of the bar', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('cards', ['kop' => 'Teams'], id: 'a'), storedStrip('cards', ['kop' => 'Agenda'], id: 'b')])
        ->withDraft([storedStrip('cards', ['kop' => 'Onze teams'], id: 'a'), storedStrip('cards', ['kop' => 'Agenda'], visible: false, id: 'b')])
        ->create();

    $response = $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertOk()
        ->assertExactJson(['draft' => [
            'live' => true,
            'changes' => 2,
            'publishable' => true,
            'discardable' => true,
            'label' => 'Concept · 2 wijzigingen',
            'discard_text' => 'Je 2 wijzigingen gaan weg. De live versie blijft zoals hij is.',
        ]]);

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('says there is nothing to publish on a live page without a draft', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a')])->create();

    $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertOk()
        ->assertJsonPath('draft.changes', 0)
        ->assertJsonPath('draft.publishable', false)
        ->assertJsonPath('draft.discardable', false)
        ->assertJsonPath('draft.label', 'Geen wijzigingen');
});

it('says a page that was never published is not live yet', function (): void {
    $page = Page::factory()->create();

    $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertOk()
        ->assertJsonPath('draft.live', false)
        ->assertJsonPath('draft.publishable', true)
        ->assertJsonPath('draft.label', 'Nog niet gepubliceerd');
});

it('counts one change in the singular', function (): void {
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();

    $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertJsonPath('draft.label', 'Concept · 1 wijziging')
        ->assertJsonPath('draft.discard_text', 'Je wijziging gaat weg.');
});

it('answers in English when the launcher locale is English', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);
    $page = Page::factory()->published()->withStrips([])->withDraft([storedStrip('cards', id: 'a'), storedStrip('cards', id: 'b')])->create();

    $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertJsonPath('draft.label', 'Draft · 2 changes')
        ->assertJsonPath('draft.discard_text', 'Your 2 changes will be gone. The live version stays as it is.');
});

it('answers 401 to a guest', function (): void {
    $page = Page::factory()->create();
    auth('made-cms')->logout();

    $this->getJson('made-cms/pages/'.$page->id.'/draft')->assertUnauthorized();
});
