<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);

    $this->teams = Page::factory()->published()->at('/teams')->create(['title' => 'Onze teams', 'slug' => 'teams']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('counts the description while the editor types and shows it in the result in google', function (): void {
    $card = visit('/made-cms/pages/'.$this->teams->id);

    $card->assertSeeIn('[data-meta-count="description"]', 'Ontbreekt · 0 van 155 tekens')
        ->assertSeeIn('[data-meta-preview="description"]', 'Google kiest nu zelf een stukje tekst van de pagina');

    $card->type('#made-cms-meta_description', 'Van de jeugd tot de eredivisie.')
        ->assertSeeIn('[data-meta-count="description"]', '31 van 155 tekens')
        ->assertSeeIn('[data-meta-preview="description"]', 'Van de jeugd tot de eredivisie.')
        ->assertNoJavaScriptErrors();

    expect($card->script("() => [document.querySelector('[data-meta-count=\"description\"]').className, document.querySelector('[data-meta-preview=\"description\"]').className]"))
        ->toBe(['veld-hint veld-teller', 'google-tekst']);
});

it('shows the default title while the title is empty, and says when google cuts a title off', function (): void {
    $card = visit('/made-cms/pages/'.$this->teams->id);

    $card->assertSeeIn('[data-meta-preview="title"]', 'Onze teams · TTV Merwestad')
        ->assertSeeIn('[data-meta-count="title"]', '26 van 60 tekens · standaardtitel');

    $card->type('#made-cms-meta_title', str_repeat('Tafeltennis ', 6))
        ->assertSeeIn('[data-meta-count="title"]', '71 van 60 tekens · Google kapt hem af');

    expect($card->script("() => document.querySelector('[data-meta-count=\"title\"]').classList.contains('te-lang')"))->toBeTrue();

    $card->clear('#made-cms-meta_title')
        ->assertSeeIn('[data-meta-preview="title"]', 'Onze teams · TTV Merwestad')
        ->assertSeeIn('[data-meta-count="title"]', '26 van 60 tekens · standaardtitel')
        ->assertNoJavaScriptErrors();
});

it('says a page google may not show never goes into the sitemap', function (): void {
    $card = visit('/made-cms/pages/'.$this->teams->id);

    $card->click('#made-cms-indexable')
        ->assertSeeIn('#made-cms-in_sitemap-hint', 'Een pagina die Google niet mag tonen, komt nooit in de sitemap.')
        ->click('#made-cms-indexable')
        ->assertSeeIn('#made-cms-in_sitemap-hint', 'Uit betekent: niet in de sitemap.')
        ->assertNoJavaScriptErrors();
});

it('saves the card and comes back to it with what it stored', function (): void {
    $card = visit('/made-cms/pages/'.$this->teams->id)->resize(1280, 800);

    $card->type('#made-cms-meta_title', 'Tafeltennis in Dordrecht: onze teams')
        ->type('#made-cms-meta_description', 'Van de jeugd tot de eredivisie.')
        ->click('#made-cms-indexable')
        ->click('#made-cms-google button[type="submit"]')
        ->assertSee('Opgeslagen. De site toont het meteen; Google neemt het over bij zijn volgende bezoek.')
        ->assertValue('#made-cms-meta_title', 'Tafeltennis in Dordrecht: onze teams')
        ->assertValue('#made-cms-meta_description', 'Van de jeugd tot de eredivisie.')
        ->assertNoJavaScriptErrors();

    expect($card->script("() => [location.hash, document.querySelector('#made-cms-google').getBoundingClientRect().top < innerHeight]"))
        ->toBe(['#made-cms-google', true]);

    $this->teams->refresh();

    expect($this->teams->meta_title)->toBe('Tafeltennis in Dordrecht: onze teams')
        ->and($this->teams->meta_description)->toBe('Van de jeugd tot de eredivisie.')
        ->and($this->teams->indexable)->toBeFalse()
        ->and($this->teams->in_sitemap)->toBeTrue();
});

it('keeps the result in google light in the dark colours of the system', function (): void {
    $card = visit('/made-cms/pages/'.$this->teams->id)->inDarkMode();

    expect($card->script(<<<'JS'
        () => {
            const colour = (selector, property) => getComputedStyle(document.querySelector(selector))[property];

            return [
                colour('.google', 'backgroundColor'),
                colour('.google-titel', 'color'),
                colour('#made-cms-meta_description', 'backgroundColor'),
                colour('#made-cms-meta_description', 'fontFamily').includes('Instrument Sans'),
            ];
        }
        JS))->toBe(['rgb(251, 250, 245)', 'rgb(26, 13, 171)', 'rgb(27, 61, 54)', true]);
});
