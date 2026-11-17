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
            'short_label' => '2 wijzigingen',
            'stop_title' => 'Je wijzigingen staan nog niet op de site',
            'stop_text' => 'Bezoekers zien je 2 wijzigingen pas na Publiceren. Je concept blijft bewaard.',
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

it('says there are no changes once a change is taken back', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a')])->create();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a/visibility', ['visible' => false])->assertOk();
    $this->getJson('made-cms/pages/'.$page->id.'/draft')->assertJsonPath('draft.changes', 1);

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a/visibility', ['visible' => true])->assertOk();

    $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertOk()
        ->assertJsonPath('draft.changes', 0)
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

it('words the choice for stopping by what visitors see', function (): void {
    $never = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->create();

    $this->getJson('made-cms/pages/'.$never->id.'/draft')
        ->assertJsonPath('draft.short_label', '1 wijziging')
        ->assertJsonPath('draft.stop_title', 'Deze pagina staat nog niet op de site')
        ->assertJsonPath('draft.stop_text', 'Bezoekers zien hem pas na Publiceren. Je concept blijft bewaard. Je gaat terug naar Pagina’s.');

    $clean = Page::factory()->create();

    $this->getJson('made-cms/pages/'.$clean->id.'/draft')->assertJsonPath('draft.short_label', 'Niet gepubliceerd');

    $live = Page::factory()->published()->withStrips([storedStrip('cards', id: 'a')])->create();

    $this->getJson('made-cms/pages/'.$live->id.'/draft')->assertJsonPath('draft.short_label', 'Geen wijzigingen');
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
