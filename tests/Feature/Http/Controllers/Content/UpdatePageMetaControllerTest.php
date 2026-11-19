<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('saves how google sees the page and goes back to the card, which says so once', function (): void {
    $page = Page::factory()->at('/teams')->create(['title' => 'Onze teams']);

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id.'/meta', [
            'meta_title' => 'Tafeltennis in Dordrecht: onze teams',
            'meta_description' => 'Van de jeugd tot de eredivisie.',
            'in_sitemap' => '1',
            'indexable' => '1',
        ])
        ->assertRedirect('/made-cms/pages/'.$page->id.'#made-cms-google');

    $page->refresh();

    expect($page->meta_title)->toBe('Tafeltennis in Dordrecht: onze teams')
        ->and($page->meta_description)->toBe('Van de jeugd tot de eredivisie.')
        ->and($page->in_sitemap)->toBeTrue()
        ->and($page->indexable)->toBeTrue();

    $this->get('made-cms/pages/'.$page->id)
        ->assertSee('Opgeslagen. De site toont het meteen; Google neemt het over bij zijn volgende bezoek.')
        ->assertDontSee('Onze teams is opgeslagen.');
    $this->get('made-cms/pages/'.$page->id)->assertDontSee('Opgeslagen. De site toont het meteen');
});

it('stores both switches off', function (): void {
    $page = Page::factory()->at('/bedankt')->create();

    $this->put('made-cms/pages/'.$page->id.'/meta', ['meta_title' => '', 'meta_description' => '', 'in_sitemap' => '0', 'indexable' => '0']);

    expect($page->refresh()->in_sitemap)->toBeFalse()
        ->and($page->indexable)->toBeFalse();
});

it('stores empty texts as none, so the page falls back again', function (): void {
    $page = Page::factory()->withMeta('Onze teams', 'Van de jeugd tot de eredivisie.')->at('/teams')->create();

    $this->put('made-cms/pages/'.$page->id.'/meta', ['meta_title' => ' ', 'meta_description' => '', 'in_sitemap' => '1', 'indexable' => '1']);

    expect($page->refresh()->meta_title)->toBeNull()
        ->and($page->meta_description)->toBeNull();
});

it('refuses a title or a description far longer than google shows, back at the card', function (): void {
    $page = Page::factory()->at('/teams')->create();

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id.'/meta', [
            'meta_title' => str_repeat('a', 121),
            'meta_description' => str_repeat('b', 321),
            'in_sitemap' => '1',
            'indexable' => '1',
        ])
        ->assertRedirect('/made-cms/pages/'.$page->id.'#made-cms-google')
        ->assertSessionHasErrors([
            'meta_title' => 'Houd de titel in Google op hooguit 120 tekens. Google toont er ongeveer 60.',
            'meta_description' => 'Houd de omschrijving in Google op hooguit 320 tekens. Google toont er ongeveer 155.',
        ]);

    expect($page->refresh()->meta_title)->toBeNull();
});

it('takes a title and a description at their longest', function (): void {
    $page = Page::factory()->at('/teams')->create();

    $this->put('made-cms/pages/'.$page->id.'/meta', [
        'meta_title' => str_repeat('a', 120),
        'meta_description' => str_repeat('b', 320),
        'in_sitemap' => '1',
        'indexable' => '1',
    ])->assertSessionHasNoErrors();

    expect(mb_strlen((string) $page->refresh()->meta_title))->toBe(120);
});

it('sends a visitor without a session to the login', function (): void {
    auth('made-cms')->logout();
    $page = Page::factory()->at('/teams')->create();

    $this->put('made-cms/pages/'.$page->id.'/meta', ['meta_title' => 'Onze teams'])->assertRedirect(route('made-cms-package.login'));

    expect($page->refresh()->meta_title)->toBeNull();
});
