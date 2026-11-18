<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useWorkbenchSite();
});

it('serves the published pages of the workbench through the macro', function (): void {
    Page::factory()->published()->at('/')->withStrips([storedStrip('hero', ['kop' => 'Welkom'])])->create(['title' => 'Home']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Home · Made CMS Workbench</title>', false)
        ->assertSee('data-cms-strip=', false)
        ->assertSeeText('Welkom');
});

it('renders the strips of a workbench page with the strips component and the workbench views', function (): void {
    $about = Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);

    Page::factory()->published()->at('/')->withStrips([
        storedStrip('hero', [
            'label' => 'Workbench',
            'kop' => 'Welkom',
            'intro' => '<p>Twee <strong>zinnen</strong>.</p>',
            'knoppen' => [['knop' => ['text' => 'Over ons', 'link' => ['page' => $about->id]]]],
        ], id: 'hero'),
        storedStrip('hero', ['kop' => 'Verborgen'], visible: false, id: 'verborgen'),
        storedStrip('cards', [
            'kop' => 'Kaarten',
            'kolommen' => '2',
            'kaarten' => [['titel' => 'Lid worden', 'tekst' => "Kom\nspelen", 'link' => ['url' => '/lid-worden']]],
        ], id: 'cards'),
    ])->create(['title' => 'Home']);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['data-cms-strip="hero"', 'data-cms-strip="cards"'], false)
        ->assertSee('<h2 data-cms-field="kop" class="hero__kop">Welkom</h2>', false)
        ->assertSee('<h3 data-cms-field="titel" class="kaart__titel">Lid worden</h3>', false)
        ->assertSee('<p>Twee <strong>zinnen</strong>.</p>', false)
        ->assertSee('href="/over-ons"', false)
        ->assertSee('data-kolommen="2"', false)
        ->assertSee('href="/lid-worden"', false)
        ->assertSee('Kom<br />', false)
        ->assertDontSee('data-cms-strip="verborgen"', false)
        ->assertDontSeeText('Verborgen');
});

it('renders the gallery of the workbench with a photo and a caption per item, and nothing for an item without a photo', function (): void {
    Storage::fake('public');
    $photo = Media::factory()->withAlt('Het eerste team')->create(['path' => 'media/01k9/teamfoto.webp', 'width' => 1600, 'height' => 1067]);

    Page::factory()->published()->at('/')->withStrips([
        storedStrip('gallery', ['fotos' => [
            ['id' => 'f1', 'foto' => $photo->id, 'bijschrift' => 'Kampioen'],
            ['id' => 'f2', 'foto' => null, 'bijschrift' => 'Nog zonder foto'],
        ]], id: 'galerij'),
    ])->create(['title' => 'Home']);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-cms-strip="galerij"', false)
        ->assertSee('data-cms-items="fotos"', false)
        ->assertSee('<img src="'.Storage::disk('public')->url('media/01k9/teamfoto.webp').'" alt="Het eerste team" width="1600" height="1067" data-cms-field="foto"', false)
        ->assertSee('<figcaption data-cms-field="bijschrift">Kampioen</figcaption>', false)
        ->assertSee('<figcaption data-cms-field="bijschrift">Nog zonder foto</figcaption>', false);
});

it('answers 404 for a draft page of the workbench', function (): void {
    Page::factory()->at('/concept')->create();

    $this->get('concept')->assertNotFound();
});

it('serves the draft of a workbench page to an editor in the edit mode', function (): void {
    Page::factory()->at('/concept')->withDraft([
        storedStrip('hero', ['kop' => 'Nog niet gepubliceerd'], id: 'nieuw'),
        storedStrip('hero', ['kop' => 'Verborgen'], visible: false, id: 'verborgen'),
    ])->create(['title' => 'Concept']);

    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('concept?made-cms=edit')
        ->assertOk()
        ->assertSee('<title>Concept · Made CMS Workbench</title>', false)
        ->assertSee('data-cms-strip="nieuw"', false)
        ->assertSeeText('Nog niet gepubliceerd')
        ->assertDontSee('data-cms-strip="verborgen"', false)
        ->assertDontSee('<made-cms-launcher', false)
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});
