<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    useStripTypes();

    $this->training = Page::factory()->published()->at('/trainingstijden')->atPosition(1)
        ->withStrips([storedStrip('hero', ['intro' => 'We trainen op dinsdag en donderdag in Mountain Network. Kom gerust een keer meespelen.'])])
        ->create(['title' => 'Trainingstijden']);
    $this->contact = Page::factory()->published()->at('/contact')->atPosition(2)
        ->withMeta('Contact', str_repeat('Bel of mail ons gerust. ', 7))
        ->create(['title' => 'Contact']);
    $this->teams = Page::factory()->published()->at('/teams')->atPosition(3)
        ->withMeta('Onze teams', 'Alle teams van de club, van de jeugd tot de eredivisie.')
        ->create(['title' => 'Onze teams']);

    $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms');
});

/**
 * The tone of the bar of a field in the panel and the width of its fill.
 */
function metaBar(string $name): string
{
    return "() => { const bar = document.querySelector('[data-meta-bar=\"{$name}\"]'); return [bar.className, bar.querySelector('.metabalk-vulling').getAttribute('width'), document.querySelector('[data-meta-said=\"{$name}\"]').textContent]; }";
}

it('works through the points: a proposed description, a description shortened, and the finished screen', function (): void {
    $screen = visit('/made-cms/meta-tags')->resize(1280, 1600);

    $screen->assertSeeIn('[data-filter="missing"]', '1 zonder omschrijving')
        ->assertSeeIn('[data-filter="long"]', '1 te lang')
        ->assertSeeIn('[data-filter="ok"]', '1 in orde')
        ->assertSeeIn('[data-filter="all"]', 'Alle 3 pagina’s')
        ->click('.kop-met-actie .knop')
        ->assertSeeIn('.metapaneel .eyebrow', 'Pagina 1 van 2')
        ->assertSeeIn('#made-cms-meta-pagina', 'Trainingstijden');

    $screen->click('[data-meta-suggest]');

    expect($screen->script("() => [document.querySelector('#made-cms-meta_description').value, document.activeElement.id, document.querySelector('[data-meta-count=\"description\"]').textContent]"))
        ->toBe(['We trainen op dinsdag en donderdag in Mountain Network. Kom gerust een keer meespelen.', 'made-cms-meta_description', '86 / 155'])
        ->and($screen->script(metaBar('description')))->toBe(['metabalk past', '55', 'Omschrijving 86 van 155 tekens']);

    $screen->click('.metapaneel button[type="submit"]')
        ->assertSee('Titel en omschrijving van Trainingstijden staan live.')
        ->assertSeeIn('.metapaneel .eyebrow', 'Pagina 2 van 2')
        ->assertSeeIn('.metavoortgang-tekst', '1 van 2 gedaan')
        ->assertSeeIn('#made-cms-meta-pagina', 'Contact');

    expect($screen->script(metaBar('description')))->toBe(['metabalk te-lang', '100', 'Omschrijving 167 tekens, te lang']);

    $screen->type('#made-cms-meta_description', 'Bel of mail ons gerust; we helpen je graag verder met je vraag over de club.');

    expect($screen->script(metaBar('description')))->toBe(['metabalk past', '49', 'Omschrijving 76 van 155 tekens']);

    $screen->click('.metapaneel button[type="submit"]')
        ->assertSee('Titel en omschrijving van Contact staan live.')
        ->assertSee('Elke pagina heeft een titel en een omschrijving die passen.')
        ->assertSeeIn('[data-filter="ok"]', '3 in orde')
        ->assertNoJavaScriptErrors();

    expect($this->training->refresh()->meta_description)->toBe('We trainen op dinsdag en donderdag in Mountain Network. Kom gerust een keer meespelen.')
        ->and($this->contact->refresh()->meta_description)->toBe('Bel of mail ons gerust; we helpen je graag verder met je vraag over de club.');
});

it('turns a bar amber near the limit and red past it while the editor types', function (): void {
    $screen = visit('/made-cms/meta-tags?page='.$this->teams->id)->resize(1280, 1600);

    $screen->type('#made-cms-meta_title', str_repeat('t', 55));

    expect($screen->script(metaBar('title')))->toBe(['metabalk krap', '92', 'Titel 55 van 60 tekens, bijna te lang']);

    $screen->type('#made-cms-meta_title', str_repeat('t', 61))
        ->assertSeeIn('[data-meta-count="title"]', '61 / 60')
        ->assertSeeIn('[data-meta-preview="title"]', str_repeat('t', 61));

    expect($screen->script(metaBar('title')))->toBe(['metabalk te-lang', '100', 'Titel 61 tekens, te lang']);

    $screen->clear('#made-cms-meta_title')
        ->assertSeeIn('[data-meta-preview="title"]', 'Onze teams · TTV Merwestad')
        ->assertNoJavaScriptErrors();

    expect($screen->script(metaBar('title')))->toBe(['metabalk past', '43', 'Titel 26 van 60 tekens']);
});

it('draws the bars in the dark colours of the system', function (): void {
    $screen = visit('/made-cms/meta-tags')->inDarkMode();

    expect($screen->script(<<<'JS'
        () => {
            const fill = (selector) => getComputedStyle(document.querySelector(selector + ' .metabalk-vulling')).fill;

            return [fill('.metarij .metabalk.ontbreekt'), fill('[data-meta-bar="title"]'), getComputedStyle(document.querySelector('.google')).backgroundColor];
        }
        JS))->toBe(['rgb(224, 112, 94)', 'rgb(69, 168, 118)', 'rgb(251, 250, 245)']);
});
