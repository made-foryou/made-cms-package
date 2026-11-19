<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

function pageScreen(Page $page): string
{
    return (string) test()->get('made-cms/pages/'.$page->id)->assertOk()->getContent();
}

it('opens a page at its own address', function (): void {
    $page = Page::factory()->at('/over-ons')->create();

    expect(route('made-cms-package.pages.show', ['page' => $page], false))->toBe('/made-cms/pages/'.$page->id);
});

it('answers 404 for a page that does not exist or is deleted', function (): void {
    $deleted = Page::factory()->trashed()->create();

    $this->get('made-cms/pages/'.missingId())->assertNotFound();
    $this->get('made-cms/pages/'.$deleted->id)->assertNotFound();
});

it('names the page, its status and its address, and links to the edit shell', function (): void {
    $page = Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);

    expect(pageScreen($page))
        ->toContain('<title>Over ons · TTV Merwestad</title>')
        ->toContain('<h1 id="made-cms-titel">Over ons</h1>')
        ->toContain('<span class="status live">Live</span>')
        ->toContain('localhost/over-ons')
        ->toContain('href="/over-ons" target="_blank" rel="noopener"')
        ->toContain('<a class="knop" href="/made-cms/pages/'.$page->id.'/edit">Bewerken op de site</a>');
});

it('only links to the site for a live page', function (): void {
    $page = Page::factory()->at('/concept')->create();

    expect(pageScreen($page))->not->toContain('Bekijk op de site');
});

it('shows the basics in a form with the other pages as a parent', function (): void {
    $home = Page::factory()->published()->atPosition(0)->at('/')->create(['title' => 'Home']);
    $about = Page::factory()->published()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    $contact = Page::factory()->published()->atPosition(2)->at('/contact')->create(['title' => 'Contact']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    expect(pageScreen($about))
        ->toContain('action="/made-cms/pages/'.$about->id.'"')
        ->toContain('<input type="hidden" name="_method" value="PUT">')
        ->toContain('value="Over ons"')
        ->toContain('value="over-ons"')
        ->toContain('<option value="" selected>Bovenaan</option>')
        ->toContain('<option value="'.$contact->id.'">Contact</option>')
        ->not->toContain('<option value="'.$home->id.'"')
        ->not->toContain('<option value="'.$team->id.'"')
        ->toContain('Plek 2 van 3 in het menu.');
});

it('counts the place in the menu among the pages the site shows there', function (): void {
    Page::factory()->published()->atPosition(0)->at('/')->create(['title' => 'Home']);
    Page::factory()->atPosition(1)->at('/jeugd')->create(['title' => 'Jeugd']);
    $about = Page::factory()->published()->atPosition(2)->at('/over-ons')->create(['title' => 'Over ons']);
    Page::factory()->published()->inMenu(false)->atPosition(3)->at('/privacy')->create(['title' => 'Privacy']);

    expect(pageScreen($about))->toContain('Plek 2 van 2 in het menu.');
});

it('says where a draft comes in the menu once it is published', function (): void {
    Page::factory()->published()->atPosition(0)->at('/')->create(['title' => 'Home']);
    $youth = Page::factory()->atPosition(1)->at('/jeugd')->create(['title' => 'Jeugd']);
    Page::factory()->atPosition(2)->at('/blog')->create(['title' => 'Blog']);
    Page::factory()->published()->atPosition(3)->at('/over-ons')->create(['title' => 'Over ons']);

    expect(pageScreen($youth))->toContain('Komt op plek 2 van 3 in het menu zodra je hem publiceert.');
});

it('names the page above that keeps a page out of the menu of the site', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);
    $team = Page::factory()->published()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);
    $privacy = Page::factory()->published()->inMenu(false)->at('/privacy')->create(['title' => 'Privacy']);
    $cookies = Page::factory()->published()->under($privacy)->at('/privacy/cookies')->create(['title' => 'Cookies']);
    $youth = Page::factory()->under($privacy)->at('/privacy/jeugd')->create(['title' => 'Jeugd']);

    expect(pageScreen($team))->toContain('Komt in het menu zodra Over ons erin staat.')
        ->and(pageScreen($cookies))->toContain('Komt in het menu zodra Privacy erin staat.')
        ->and(pageScreen($youth))->toContain('Komt in het menu zodra Privacy erin staat.');
});

it('explains the switch for a page outside the menu', function (): void {
    $page = Page::factory()->published()->inMenu(false)->at('/privacy')->create(['title' => 'Privacy']);

    expect(pageScreen($page))
        ->toContain('Uit betekent: alleen te vinden via het adres, niet in het menu.')
        ->not->toContain('Plek ');
});

it('offers the switch for visitors on in the basics of a page visitors see', function (): void {
    $page = Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);

    expect(pageScreen($page))
        ->toContain('<label class="schakel-label" for="made-cms-visible">Zichtbaar voor bezoekers</label>')
        ->toContain('Uit betekent: alleen via de directe link, niet in menu, sitemap of Google.')
        ->toContain('id="made-cms-visible" name="visible" value="1" checked aria-describedby');
});

it('names a hidden page hidden, with the switch off and its place in the menu on hold', function (): void {
    Page::factory()->published()->atPosition(0)->at('/')->create(['title' => 'Home']);
    $page = Page::factory()->published()->hidden()->atPosition(1)->at('/privacy')->create(['title' => 'Privacy']);

    expect(pageScreen($page))
        ->toContain('<span class="status">Verborgen</span>')
        ->toContain('id="made-cms-visible" name="visible" value="1" aria-describedby')
        ->toContain('Staat niet in het menu zolang hij verborgen is voor bezoekers.')
        ->not->toContain('Plek ')
        ->toContain('href="/privacy" target="_blank" rel="noopener"');
});

it('names a hidden page that was never published a draft', function (): void {
    $page = Page::factory()->hidden()->at('/jeugd')->create(['title' => 'Jeugd']);

    expect(pageScreen($page))->toContain('<span class="status">Concept</span>')
        ->not->toContain('<span class="status">Verborgen</span>');
});

it('names a hidden page among the pages under a page', function (): void {
    $about = Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);
    Page::factory()->published()->hidden()->under($about)->at('/over-ons/archief')->create(['title' => 'Archief']);

    expect(pageScreen($about))->toContain('<span class="status">Verborgen</span>');
});

it('chooses the current parent', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    expect(pageScreen($team))->toContain('<option value="'.$about->id.'" selected>Over ons</option>');
});

it('keeps the address and the place of the home page out of the form, and the home page itself', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home']);

    expect(pageScreen($home))
        ->not->toContain('name="slug"')
        ->not->toContain('name="parent_id"')
        ->not->toContain('name="visible"')
        ->not->toContain('Weghalen');
});

it('lists the pages under it and links to a new one below it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    expect(pageScreen($about))
        ->toContain('href="/made-cms/pages/new?parent='.$about->id.'"')
        ->toContain('/over-ons/team')
        ->toContain('href="/made-cms/pages/'.$team->id.'"');
});

it('only offers to delete a page without pages under it', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    expect(pageScreen($about))
        ->toContain('Onder Over ons liggen nog pagina’s.')
        ->not->toContain('<input type="hidden" name="_method" value="DELETE">');

    expect(pageScreen($team))
        ->toContain('Ja, verwijder Team')
        ->toContain('<input type="hidden" name="_method" value="DELETE">');
});

it('offers to hide a page next to deleting it, also when it cannot be deleted', function (): void {
    $about = Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);
    $team = Page::factory()->published()->under($about)->at('/over-ons/team')->create(['title' => 'Team']);

    foreach ([$about, $team] as $page) {
        expect(pageScreen($page))
            ->toContain('Liever niet weg? Verberg '.$page->title.' dan: hij blijft bereikbaar via de directe link, maar staat niet meer in het menu, de sitemap of Google.')
            ->toContain('action="/made-cms/pages/'.$page->id.'/visibility"')
            ->toContain('<input type="hidden" name="visible" value="0">')
            ->toContain('<button type="submit" class="knop stil">Verbergen</button>');
    }
});

it('offers to show a hidden page again', function (): void {
    $page = Page::factory()->published()->hidden()->at('/privacy')->create(['title' => 'Privacy']);

    expect(pageScreen($page))
        ->toContain('Privacy is verborgen: alleen bereikbaar via de directe link, niet in het menu, de sitemap of Google.')
        ->toContain('<input type="hidden" name="visible" value="1">')
        ->toContain('<button type="submit" class="knop stil">Weer tonen</button>')
        ->not->toContain('Liever niet weg?');
});

it('marks the page as current in the menu', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);

    expect(pageScreen($page))->toContain('href="/made-cms/pages/'.$page->id.'" aria-current="page"');
});

it('says once that a new page is ready', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons']);

    $this->post('made-cms/pages', ['title' => 'Jeugd', 'parent_id' => (string) $about->id, 'in_menu' => '1']);
    $page = Page::query()->where('title', 'Jeugd')->sole();

    $this->get('made-cms/pages/'.$page->id)
        ->assertSee('Jeugd staat klaar, nog leeg en nog niet zichtbaar.')
        ->assertSee('Hij staat als concept op /over-ons/jeugd, onder Over ons, achteraan in het menu.')
        ->assertSee('href="/made-cms/pages/'.$page->id.'/edit">Nu vullen op de site</a>', false)
        ->assertSee('href="/made-cms/pages/'.$page->id.'">Eerst het overzicht</a>', false)
        ->assertSee('href="/made-cms/pages">Later</a>', false);

    expect(pageScreen($page))->not->toContain('staat klaar');
});

it('shows how google sees a page without a title or a description of its own', function (): void {
    $page = Page::factory()->published()->at('/over-ons/team')->create(['title' => 'Ons team']);

    expect(pageScreen($page))
        ->toContain('<h2 id="made-cms-google-kop">Hoe Google hem ziet</h2>')
        ->toContain('<span class="google-adres">localhost › over-ons › team</span>')
        ->toContain('<span class="google-titel" data-meta-preview="title">Ons team · TTV Merwestad</span>')
        ->toContain('<span class="google-tekst leeg" data-meta-preview="description">Google kiest nu zelf een stukje tekst van de pagina, omdat de omschrijving leeg is.</span>')
        ->toContain('name="meta_title" value="" placeholder="Ons team · TTV Merwestad"')
        ->toContain('data-meta-count="title">24 van 60 tekens · standaardtitel</p>')
        ->toContain('<p class="veld-hint veld-teller ontbreekt" id="made-cms-meta_description-teller" data-meta-count="description">Ontbreekt · 0 van 155 tekens</p>')
        ->toContain('id="made-cms-in_sitemap" name="in_sitemap" value="1" checked')
        ->toContain('id="made-cms-indexable" name="indexable" value="1" checked')
        ->toContain('<span class="veld-hint" id="made-cms-in_sitemap-hint">Uit betekent: niet in de sitemap. De pagina blijft bereikbaar en Google vindt hem nog via links.</span>');
});

it('shows the title and the description of its own, and counts them', function (): void {
    $page = Page::factory()->published()->withMeta('Tafeltennis in Dordrecht', 'Van de jeugd tot de eredivisie.')->at('/teams')->create(['title' => 'Onze teams']);

    expect(pageScreen($page))
        ->toContain('<span class="google-titel" data-meta-preview="title">Tafeltennis in Dordrecht</span>')
        ->toContain('<span class="google-tekst" data-meta-preview="description">Van de jeugd tot de eredivisie.</span>')
        ->toContain('name="meta_title" value="Tafeltennis in Dordrecht" placeholder="Onze teams · TTV Merwestad"')
        ->toContain('data-meta-count="title">24 van 60 tekens</p>')
        ->toContain('data-meta-count="description">31 van 155 tekens</p>')
        ->toContain('>Van de jeugd tot de eredivisie.</textarea>');
});

it('says when google cuts a title off', function (): void {
    $page = Page::factory()->withMeta(str_repeat('Tafeltennis ', 6))->at('/teams')->create();

    expect(pageScreen($page))
        ->toContain('<p class="veld-hint veld-teller te-lang" id="made-cms-meta_title-teller" data-meta-count="title">71 van 60 tekens · Google kapt hem af</p>');
});

it('names the home page in google after the site alone', function (): void {
    $home = Page::factory()->published()->at('/')->create(['title' => 'Home']);

    expect(pageScreen($home))
        ->toContain('<span class="google-adres">localhost</span>')
        ->toContain('<span class="google-titel" data-meta-preview="title">TTV Merwestad</span>');
});

it('says that a page google may not show never goes into the sitemap', function (): void {
    $page = Page::factory()->noindex()->outOfSitemap()->at('/bedankt')->create();

    expect(pageScreen($page))
        ->toContain('id="made-cms-in_sitemap" name="in_sitemap" value="1" aria-describedby')
        ->toContain('id="made-cms-indexable" name="indexable" value="1" aria-describedby')
        ->toContain('<span class="veld-hint" id="made-cms-in_sitemap-hint">Een pagina die Google niet mag tonen, komt nooit in de sitemap.</span>');
});

it('says in the google card that a hidden page stays out of google and the sitemap', function (): void {
    $page = Page::factory()->published()->hidden()->at('/privacy')->create();

    expect(pageScreen($page))
        ->toContain('Verborgen voor bezoekers: Google toont hem niet en hij komt niet in de sitemap, wat de schakelaars hieronder ook zeggen.')
        ->toContain('id="made-cms-indexable" name="indexable" value="1" checked aria-describedby');

    $page->hidden = false;
    $page->save();

    expect(pageScreen($page))->not->toContain('Verborgen voor bezoekers:');
});

it('links to the meta overview only for an editor who opens it', function (Role $role, bool $linked): void {
    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms');
    $page = Page::factory()->at('/teams')->create();

    expect(str_contains(pageScreen($page), '<a class="tekstlink klein" href="/made-cms/meta-tags">Alle pagina’s in Meta-overzicht</a>'))->toBe($linked);
})->with([
    'the seo specialist' => [Role::SeoSpecialist, true],
    'an admin' => [Role::Admin, true],
    'the content manager' => [Role::ContentManager, false],
]);

describe('the card "Hoe je link gedeeld wordt"', function (): void {
    beforeEach(function (): void {
        Storage::fake('public', ['url' => 'http://localhost/storage']);
    });

    /**
     * The texts of the notices of the card, as one line each.
     *
     * @return list<string>
     */
    function shareNotices(string $html): array
    {
        $start = strpos($html, 'data-slot="share-notices"');
        $part = $start === false ? '' : substr($html, $start, (int) strpos($html, 'class="deelvoet"', $start) - $start);
        preg_match_all('/<span class="melding-tekst">(.*?)<\/span>\s*<\/div>/s', $part, $matches);

        return array_map(static fn (string $text): string => trim((string) preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($text)))), $matches[1]);
    }

    it('comes right after the card of google, with the shared link as an app shows it', function (): void {
        $media = Media::factory()->in(MediaFolder::factory()->create(['name' => 'Clubleven']))->withAlt('Leden aan de tafels')
            ->create(['name' => 'clubavond', 'width' => 1200, 'height' => 630, 'size' => 184_000]);
        $page = Page::factory()->published()->withMeta(null, 'Wie we zijn.')->withShareImage($media)->at('/over-ons')->create(['title' => 'Over ons']);

        $html = pageScreen($page);

        expect(strpos($html, 'id="made-cms-share"'))->toBeGreaterThan((int) strpos($html, 'id="made-cms-google"'))
            ->and($html)->toContain('<h2 id="made-cms-share-kop">Hoe je link gedeeld wordt</h2>')
            ->toContain('action="/made-cms/pages/'.$page->id.'/share-image"')
            ->toContain('<input type="hidden" name="share_image" value="'.$media->id.'" data-slot="share-value">')
            ->toContain('<img src="http://localhost/storage/'.$media->thumb_path.'" alt="" data-slot="share-preview-image">')
            ->toContain('<span class="deellink-domein">localhost</span>')
            ->toContain('<span class="deellink-titel" data-slot="share-title">Over ons · TTV Merwestad</span>')
            ->toContain('<span class="deellink-omschrijving" data-slot="share-description">Wie we zijn.</span>')
            ->toContain('<span class="deelveld-naam" data-slot="share-name">clubavond.webp</span>')
            ->toContain('<span class="sectienoot" data-slot="share-meta">1200 × 630 · 184 kB · uit map Clubleven</span>')
            ->toContain('data-slot="share-alt-text">Alt: Leden aan de tafels</span>')
            ->toContain('href="#made-cms-google">Hoe Google hem ziet</a>')
            ->not->toContain('Delen werkt ook als Google deze pagina niet toont')
            ->and(shareNotices($html))->toBe([]);
    });

    it('says the page uses the default image of the site, or that a link shows no image at all', function (?string $default, string $notice): void {
        config(['made-cms-package.meta.share_image' => $default]);
        $page = Page::factory()->at('/over-ons')->create();

        $html = pageScreen($page);

        expect(shareNotices($html))->toBe([$notice])
            ->and($html)->toContain('<input type="hidden" name="share_image" value="" data-slot="share-value">')
            ->toContain('data-slot="share-empty">');
    })->with([
        'a default' => ['/images/deel.jpg', 'Deze pagina gebruikt de standaardafbeelding van de site. Kies een eigen afbeelding om dat te veranderen.'],
        'none' => [null, 'Deze pagina heeft geen afbeelding en de site heeft geen standaardafbeelding. In een gedeelde link staan dan alleen de titel en de omschrijving, zonder plaatje. Kies een afbeelding om dat te veranderen.'],
    ]);

    it('warns about a small photo, another shape and a missing alt text, without stopping a save', function (int $width, int $height, ?string $alt, array $notices): void {
        $media = Media::factory()->create(['width' => $width, 'height' => $height, 'alt' => $alt]);
        $page = Page::factory()->withShareImage($media)->at('/over-ons')->create();

        expect(shareNotices(pageScreen($page)))->toBe($notices);
    })->with([
        'small' => [480, 252, 'Toernooi', ['Deze afbeelding is klein. Hij is 480 × 252 px; aanbevolen is 1200 × 630. Onder ± 600 px breed wordt hij vaak klein of niet getoond. Opslaan kan gewoon.']],
        'three by two' => [1600, 1067, 'Training', ['Hij wordt bijgesneden. In een gedeelde link krijgt de afbeelding de verhouding 1,91 : 1, dus boven en onder valt samen ± 21% weg (gedimd in het kleine beeld). Met Bijsnijden kies je zelf wat blijft. Opslaan kan gewoon.']],
        'without an alt text' => [1200, 630, null, ['Alt-tekst ontbreekt. Vul die in Media in, dan geldt hij overal. Alt-tekst invullen →']],
    ]);

    it('says why a file the page holds is not shared', function (): void {
        $media = Media::factory()->create(['mime' => 'image/svg+xml']);
        $page = Page::factory()->withShareImage($media)->at('/over-ons')->create();

        expect(shareNotices(pageScreen($page)))
            ->toBe(['Deze afbeelding wordt niet gedeeld. Apps tonen geen svg bij een gedeelde link. Kies een foto, bijvoorbeeld jpg, png of webp.']);
    });

    it('says that sharing works for a page google may not show', function (): void {
        $page = Page::factory()->noindex()->at('/bedankt')->create();

        expect(pageScreen($page))->toContain('Delen werkt ook als Google deze pagina niet toont. De schakelaar bij Hoe Google hem ziet gaat alleen over zoekresultaten.');
    });

    it('says once that it is saved, after saving', function (): void {
        $media = Media::factory()->create();
        $page = Page::factory()->at('/over-ons')->create();

        $this->put('made-cms/pages/'.$page->id.'/share-image', ['share_image' => (string) $media->id]);

        expect(pageScreen($page))->toContain('data-slot="share-saved"')
            ->toContain('Een link die je nu deelt, toont deze afbeelding. Apps onthouden de oude soms nog even.')
            ->and(pageScreen($page))->not->toContain('data-slot="share-saved"');
    });

    it('shows a refusal at the card, with the image the page still has', function (): void {
        $kept = Media::factory()->create(['name' => 'clubavond']);
        $page = Page::factory()->withShareImage($kept)->at('/over-ons')->create();

        $this->from('/made-cms/pages/'.$page->id)->put('made-cms/pages/'.$page->id.'/share-image', ['share_image' => (string) Media::factory()->document()->create()->id]);

        expect(pageScreen($page))->toContain('<div class="melding alarm" role="alert">')
            ->toContain('<strong>Opslaan is niet gelukt.</strong> Dit bestand is geen afbeelding. Kies een foto. De afbeelding van deze pagina is niet veranderd.')
            ->toContain('name="share_image" value="'.$kept->id.'"');
    });

    it('links to the media library and moves files in the crop step only for an editor who opens it', function (Role $role, bool $media): void {
        $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms');
        $page = Page::factory()->withShareImage(Media::factory()->create())->at('/over-ons')->create();

        $html = pageScreen($page);

        expect(['link' => str_contains($html, 'href="/made-cms/media?file='), 'crop' => str_contains(implode(' ', shareNotices($html)), 'Vul die in bij Bijsnijden, dan geldt hij overal.'), 'folder' => str_contains($html, 'data-slot="crop-folder"')])
            ->toBe(['link' => $media, 'crop' => ! $media, 'folder' => $media]);
    })->with([
        'the content manager' => [Role::ContentManager, true],
        'the owner' => [Role::Owner, false],
        'the seo specialist' => [Role::SeoSpecialist, false],
    ]);

    it('brings the picker, the crop step and their scripts along once', function (): void {
        $html = pageScreen(Page::factory()->at('/over-ons')->create());

        expect(substr_count($html, 'data-media-picker'))->toBe(1)
            ->and(substr_count($html, 'data-media-crop'))->toBe(1)
            ->and($html)->toMatch('#launcher/upload\.js\?v=\w+".*launcher/crop\.js\?v=\w+".*launcher/media\.js\?v=\w+".*launcher/share\.js\?v=\w+"#s');
    });
});
