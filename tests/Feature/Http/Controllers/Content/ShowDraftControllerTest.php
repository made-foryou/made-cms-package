<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\PublishPage;
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
            'stop_text' => 'Bezoekers zien je 2 wijzigingen pas na Publiceren. Je concept blijft bewaard. Je gaat terug naar Pagina’s.',
            'exits' => ['stop' => '/made-cms/pages/'.$page->id, 'publish' => '/made-cms/pages/'.$page->id],
            'scheduled' => null,
        ]]);

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('says when the draft goes live by itself, and that throwing it away takes the plan along', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 14:00:00'));
    $page = Page::factory()->published()
        ->withStrips([storedStrip('cards', ['kop' => 'Teams'], id: 'a')])
        ->withDraft([storedStrip('cards', ['kop' => 'Onze teams'], id: 'a')])
        ->scheduledAt(CarbonImmutable::parse('2026-12-14 09:00:00'))
        ->create();

    $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertOk()
        ->assertJsonPath('draft.scheduled', [
            'label' => 'Gepland · 14 dec 09:00',
            'short_label' => '14 dec 09:00',
            'text' => 'Je concept gaat op 14 december om 09:00 live, met wat je tot dan wijzigt.',
        ])
        ->assertJsonPath('draft.discard_text', 'Je wijziging gaat weg. De live versie blijft zoals hij is. De planning gaat ook weg.');
});

it('says when the planned moment passed without a publication', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-27 14:00:00'));
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->scheduledAt(CarbonImmutable::parse('2026-11-27 09:00:00'))->create();

    $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertJsonPath('draft.scheduled.label', 'Gepland · vandaag 09:00')
        ->assertJsonPath('draft.scheduled.text', 'Je concept stond gepland vandaag om 09:00, maar is nog niet gepubliceerd.');
});

it('says when the draft goes live in English', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);
    $this->travelTo(CarbonImmutable::parse('2026-11-27 14:00:00'));
    $page = Page::factory()->withDraft([storedStrip('cards', id: 'a')])->scheduledAt(CarbonImmutable::parse('2026-11-28 09:00:00'))->create();

    $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertJsonPath('draft.scheduled', [
            'label' => 'Planned · tomorrow 9:00 AM',
            'short_label' => 'tomorrow 9:00 AM',
            'text' => 'Your draft goes live tomorrow at 9:00 AM, with what you change until then.',
        ]);
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

it('hands the bar the addresses it leaves for, as the page stands now', function (): void {
    $page = Page::factory()->at('/concept')->withDraft([storedStrip('cards', id: 'a')])->create();
    $pages = '/made-cms/pages/'.$page->id;

    $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertJsonPath('draft.exits', ['stop' => $pages, 'publish' => '/concept'])
        ->assertJsonPath('draft.stop_text', 'Bezoekers zien hem pas na Publiceren. Je concept blijft bewaard. Je gaat terug naar Pagina’s.');

    PublishPage::run($page);

    // Published, the page is on the site: stopping now leaves for it, and the choice no longer says Pagina’s.
    $this->getJson('made-cms/pages/'.$page->id.'/draft')
        ->assertJsonPath('draft.exits', ['stop' => '/concept', 'publish' => '/concept'])
        ->assertJsonPath('draft.stop_title', 'Je wijzigingen staan nog niet op de site');
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
