<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Controllers\Access\LauncherAssetController;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    useStripTypes();
});

/**
 * The configuration the edit shell hands to its script.
 *
 * @return array<string, mixed>
 */
function editShellConfig(string $html): array
{
    preg_match('/<script type="application\/json" data-config>(.*?)<\/script>/s', $html, $match);

    /** @var array<string, mixed> */
    return json_decode($match[1] ?? 'null', true, flags: JSON_THROW_ON_ERROR);
}

it('opens the edit shell of a page at an address under the cms prefix', function (): void {
    $page = Page::factory()->at('/over-ons')->create();

    expect(route('made-cms-package.pages.edit', ['page' => $page], false))->toBe('/made-cms/pages/'.$page->id.'/edit');
});

it('opens the edit shell for every role that opens the pages screen', function (Role $role): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->get('made-cms/pages/'.$page->id.'/edit')
        ->assertOk()
        ->assertSee('<title>Over ons bewerken · TTV Merwestad</title>', false);
})->with(Role::cases());

it('sends a guest to the login page', function (): void {
    $page = Page::factory()->at('/over-ons')->create();

    $this->get('made-cms/pages/'.$page->id.'/edit')
        ->assertRedirect(route('made-cms-package.login'));
});

it('answers 404 for a page that does not exist', function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/999/edit')
        ->assertNotFound();
});

it('is never stored by a cache, framed or indexed', function (): void {
    $page = Page::factory()->at('/over-ons')->create();

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/'.$page->id.'/edit')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertDontSee('<made-cms-launcher', false);

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('shows the page in the edit mode of the site in a frame', function (string $path, string $src): void {
    $page = Page::factory()->at($path)->create(['title' => 'Over ons']);

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/'.$page->id.'/edit')
        ->assertSee('<iframe class="bewerkframe" src="'.$src.'" title="De pagina Over ons zoals bezoekers hem zien" name="made-cms-site"></iframe>', false);
})->with([
    'home' => ['/', '/?made-cms=edit'],
    'page' => ['/over-ons', '/over-ons?made-cms=edit'],
]);

it('says there is nothing to show for a page without an address', function (): void {
    $page = Page::factory()->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/'.$page->id.'/edit')
        ->assertDontSee('<iframe', false)
        ->assertSee('Deze pagina heeft nog geen adres op de site');
});

it('names the site, the page and its status in the bar', function (): void {
    $draft = Page::factory()->at('/concept')->create(['title' => 'Nieuw']);
    $live = Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);
    $editor = Editor::factory()->create();

    $this->actingAs($editor, 'made-cms')
        ->get('made-cms/pages/'.$draft->id.'/edit')
        ->assertSeeInOrder(['TTV Merwestad', 'Nieuw', '<span class="status" data-slot="bar-page">Concept</span>', 'Voorvertoning', 'Publiceren'], false)
        ->assertSee('href="/made-cms/start"', false);

    $this->actingAs($editor, 'made-cms')
        ->get('made-cms/pages/'.$live->id.'/edit')
        ->assertSee('<span class="status live" data-slot="bar-page">Live</span>', false);
});

it('counts the changes of the draft in the bar and offers to publish or discard them', function (): void {
    $page = Page::factory()->published()->at('/over-ons')
        ->withStrips([storedStrip('cards', ['kop' => 'Teams'], id: 'a')])
        ->withDraft([storedStrip('cards', ['kop' => 'Onze teams'], id: 'a')])
        ->create();

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')->get('made-cms/pages/'.$page->id.'/edit');
    $html = (string) $response->getContent();

    $response->assertSeeInOrder(['Concept · 1 wijziging', 'Concept weggooien', 'Je wijziging gaat weg. De live versie blijft zoals hij is.', 'Ja, gooi het concept weg', 'Voorvertoning', 'Publiceren'], false)
        ->assertSee('href="/over-ons?made-cms=preview" target="_blank" rel="noopener"', false)
        ->assertSee(LauncherAssetController::url('bar.js'), false);

    expect($html)->toMatch('/<details class="bevestig bewerkbalk-weggooien" data-slot="bar-discard">/')
        ->and($html)->toMatch('/<button type="button" class="bewerkbalk-knop" data-slot="bar-publish">/');
});

it('hides discarding and disables publishing when everything is live', function (): void {
    $page = Page::factory()->published()->at('/over-ons')->withStrips([storedStrip('cards', id: 'a')])->create();

    $html = (string) $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/'.$page->id.'/edit')
        ->assertSee('Geen wijzigingen')
        ->getContent();

    expect($html)->toMatch('/<details class="bevestig bewerkbalk-weggooien" data-slot="bar-discard" hidden>/')
        ->and($html)->toMatch('/<button type="button" class="bewerkbalk-knop" data-slot="bar-publish" disabled>/');
});

it('offers no preview for a page without an address', function (): void {
    $page = Page::factory()->create();

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/'.$page->id.'/edit')
        ->assertDontSee('made-cms=preview', false)
        ->assertDontSee('Voorvertoning');
});

it('hands the bar the addresses of the draft, its state and its texts', function (): void {
    $page = Page::factory()->at('/over-ons')->withDraft([storedStrip('hero', id: 'a')])->create();

    $config = editShellConfig((string) $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/'.$page->id.'/edit')
        ->getContent());

    expect($config['urls']['draft'])->toBe('/made-cms/pages/'.$page->id.'/draft')
        ->and($config['urls']['publish'])->toBe('/made-cms/pages/'.$page->id.'/publish')
        ->and($config['draft'])->toBe([
            'live' => false,
            'changes' => 1,
            'publishable' => true,
            'discardable' => true,
            'label' => 'Concept · 1 wijziging',
            'discard_text' => 'Je wijziging gaat weg.',
        ])
        ->and($config['texts']['bar'])->toMatchArray([
            'live' => 'Live',
            'draft' => 'Concept',
            'published' => 'Gepubliceerd',
            'publish_invalid' => 'Niet gepubliceerd: :strip — :message',
        ]);
});

it('hands the strips of the working copy and the texts to the script', function (): void {
    $page = Page::factory()->at('/over-ons')
        ->withStrips([storedStrip('hero', id: 'a')])
        ->withDraft([storedStrip('hero', id: 'a'), storedStrip('cards', id: 'b', visible: false)])
        ->create();

    $config = editShellConfig((string) $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/'.$page->id.'/edit')
        ->getContent());

    expect($config['strips'])->toBe([
        ['id' => 'a', 'type' => 'hero', 'label' => 'Hero', 'visible' => true, 'known' => true, 'fields' => '6 velden', 'summary' => 'Welkom'],
        ['id' => 'b', 'type' => 'cards', 'label' => 'Kaarten', 'visible' => false, 'known' => true, 'fields' => '1 veld', 'summary' => null],
    ])->and($config['counts'])->toBe('2 stroken, 1 verborgen')
        ->and($config['texts'])->toMatchArray([
            'position' => 'Strook :n van :m',
            'unknown' => 'Strook',
        ])->and($config['texts']['missing'])->toContain('data-cms-strip=":id"');
});

it('hands the opbouw the catalogue of strip types, sorted by name, and the address to add a strip', function (): void {
    $page = Page::factory()->at('/over-ons')->create();

    $config = editShellConfig((string) $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/'.$page->id.'/edit')
        ->getContent());

    expect($config['types'])->toBe([
        ['key' => 'hero', 'label' => 'Hero', 'description' => null, 'fields' => '6 velden'],
        ['key' => 'cards', 'label' => 'Kaarten', 'description' => 'Een rij kaarten met een titel.', 'fields' => '1 veld'],
    ])->and($config['urls']['strips'])->toBe('/made-cms/pages/'.$page->id.'/strips')
        ->and($config['counts'])->toBe('Nog geen stroken');
});

it('hands the side panel the address of a strip, the csrf token and its texts', function (): void {
    $page = Page::factory()->at('/over-ons')->withDraft([storedStrip('hero', id: 'a')])->create();

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')->get('made-cms/pages/'.$page->id.'/edit');
    $config = editShellConfig((string) $response->getContent());

    expect($config['urls']['strip'])->toBe('/made-cms/pages/'.$page->id.'/strips/:strip')
        ->and($config['csrf_token'])->toBe(session()->token())
        ->and($config['texts']['hidden'])->toBe('Verborgen')
        ->and($config['texts']['panel'])->toMatchArray([
            'saved' => 'Opgeslagen als concept',
            'add' => ':item toevoegen',
            'position' => 'Item :n van :m',
        ]);

    $response->assertSee('Strook zichtbaar op de site')
        ->assertSee('Opslaan als concept')
        ->assertSee('<template data-field="rich_text">', false)
        ->assertSee(LauncherAssetController::url('panel.js'), false);
});

it('speaks English when the launcher locale is English', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);
    $page = Page::factory()->at('/over-ons')->create(['title' => 'About']);

    $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/pages/'.$page->id.'/edit')
        ->assertSee('<title>Edit About · TTV Merwestad</title>', false)
        ->assertSee('Click a strip on the site to edit its content.')
        ->assertSee('<span class="status" data-slot="bar-page">Draft</span>', false);
});
