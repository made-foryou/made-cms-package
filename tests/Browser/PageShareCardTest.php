<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\SetPageShareImage;
use Made\CmsPackage\Content\Actions\UploadMedia;
use Made\CmsPackage\Content\Data\UploadedMedia;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    Storage::fake('public');

    // The fake media disk has no address of its own: this serves its files, so a thumbnail loads.
    Route::get('storage/{path}', fn (string $path) => response()->file(Storage::disk('public')->path($path)))->where('path', '.*');

    $this->clubavond = UploadMedia::run(new UploadedMedia(photo(1200, 630, 'jpg', 'Clubavond.jpg'), 'Clubavond.jpg'));
    $this->clubavond->update(['alt' => 'Leden aan de tafels']);
    $this->training = UploadMedia::run(new UploadedMedia(photo(1600, 1067, 'jpg', 'Training dinsdag.jpg'), 'Training dinsdag.jpg'));
    $this->page = Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * A JavaScript expression for a slot of the card "Hoe je link gedeeld wordt".
 */
function shareSlot(string $slot): string
{
    return "document.querySelector('[data-slot=\"share-{$slot}\"]')";
}

/**
 * A script for $page->script(): the texts of the notices of the card.
 */
function shareNoticeTexts(): string
{
    return "() => [...document.querySelectorAll('[data-slot=\"share-notices\"] .melding-tekst')].map((text) => text.textContent.replace(/\\s+/g, ' ').trim())";
}

it('chooses an image from media, shows the shared link with it and saves it', function (): void {
    $card = visit('/made-cms/pages/'.$this->page->id)->resize(1280, 900);

    $card->assertSeeIn('[data-slot="share-empty"]', 'Nog geen eigen afbeelding')
        ->assertAttribute('[data-slot="share-save"]', 'disabled', '')
        ->click('[data-slot="share-pick"]');

    expect($card->script(waitUntil(pickerShows(['training-dinsdag', 'clubavond']))))->toBeTrue()
        ->and($card->script("() => document.querySelector('[data-slot=\"media-picker-for\"]').textContent.trim()"))
        ->toBe('voor Afbeelding in Hoe je link gedeeld wordt · aanbevolen 1200 × 630 px');

    $card->click(pickerTile($this->training))
        ->click('[data-slot="media-picker-use"]');

    expect($card->script(waitUntil('! '.shareSlot('unsaved').'.hidden')))->toBeTrue()
        ->and($card->script(shareNoticeTexts()))->toBe([
            'Hij wordt bijgesneden. In een gedeelde link krijgt de afbeelding de verhouding 1,91 : 1, dus boven en onder valt samen ± 21% weg (gedimd in het kleine beeld). Met Bijsnijden kies je zelf wat blijft. Opslaan kan gewoon.',
            'Alt-tekst ontbreekt. Vul die in Media in, dan geldt hij overal. Alt-tekst invullen →',
        ])
        ->and($card->script('() => ['.shareSlot('mini').'.dataset.axis, getComputedStyle('.shareSlot('mini').").getPropertyValue('--cut').trim()]"))->toBe(['y', '10.5%'])
        ->and($card->script(waitUntil(shareSlot('preview-image').'.naturalWidth > 0')))->toBeTrue();

    $card->assertSeeIn('[data-slot="share-name"]', 'training-dinsdag.webp')
        ->assertSeeIn('[data-slot="share-alt-text"]', 'Alt: ontbreekt')
        ->assertVisible('[data-slot="share-crop"]')
        ->click('[data-slot="share-save"]')
        ->assertSee('Een link die je nu deelt, toont deze afbeelding. Apps onthouden de oude soms nog even.')
        ->assertNoJavaScriptErrors();

    expect($card->script('() => location.hash'))->toBe('#made-cms-share')
        ->and($this->page->refresh()->share_image_id)->toBe($this->training->id)
        ->and(MediaUsage::query()->where('media_id', $this->training->id)->exists())->toBeTrue();
});

it('uploads a photo, hands it to the crop step for its alt text, and waits for it before saving', function (): void {
    $card = visit('/made-cms/pages/'.$this->page->id)->resize(1280, 900);

    // The next upload waits for window.release(), so the card can be seen while it travels.
    $card->script(<<<'JS'
        () => {
            const send = XMLHttpRequest.prototype.send;

            XMLHttpRequest.prototype.send = function (...body) {
                XMLHttpRequest.prototype.send = send;
                window.release = () => send.apply(this, body);
            };
        }
        JS);

    $card->attach('[data-slot="share-upload"] [data-slot="upload-input"]', photo(1200, 630, 'jpg', 'Open dag.jpg'));

    expect($card->script(waitUntil('! '.shareSlot('waiting').'.hidden')))->toBeTrue()
        ->and($card->script('() => ['.shareSlot('upload').'.hidden, '.shareSlot('save').'.disabled, '.shareSlot('pick').'.disabled]'))->toBe([false, true, true]);

    $card->script('() => window.release()');

    expect($card->script(waitUntil("document.querySelector('[data-media-crop]').open")))->toBeTrue();

    $card->type('[data-slot="crop-alt"]', 'Bezoekers op de open dag')
        ->click('[data-slot="crop-keep"]');

    // The crop step tells the stored file first (made-cms:media-changed), the field closes once it has it back.
    expect($card->script(waitUntil(shareSlot('alt-text').".textContent === 'Alt: Bezoekers op de open dag' && ".shareSlot('upload').'.hidden')))->toBeTrue()
        ->and($card->script('() => ['.shareSlot('upload').'.hidden, '.shareSlot('save').'.disabled, '.shareSlot('name').'.textContent]'))
        ->toBe([true, false, 'open-dag.webp'])
        ->and($card->script(shareNoticeTexts()))->toBe([]);

    $card->click('[data-slot="share-save"]')
        ->assertSee('Een link die je nu deelt, toont deze afbeelding.')
        ->assertNoJavaScriptErrors();

    $uploaded = Media::query()->where('name', 'open-dag')->sole();

    expect($this->page->refresh()->share_image_id)->toBe($uploaded->id)
        ->and($uploaded->alt)->toBe('Bezoekers op de open dag');
});

it('takes the image away, so a shared link shows the default of the site', function (): void {
    config(['made-cms-package.meta.share_image' => '/storage/'.$this->clubavond->path]);
    SetPageShareImage::run($this->page, $this->training->id);
    $card = visit('/made-cms/pages/'.$this->page->id)->resize(1280, 900);

    $card->click('[data-slot="share-remove"]');

    expect($card->script(shareNoticeTexts()))->toBe(['Deze pagina gebruikt de standaardafbeelding van de site. Kies een eigen afbeelding om dat te veranderen.'])
        ->and($card->script('() => ['.shareSlot('file').'.hidden, '.shareSlot('empty').'.hidden, '.shareSlot('preview').'.hidden, new URL('.shareSlot('preview-image').'.src).pathname]'))
        ->toBe([true, false, false, '/storage/'.$this->clubavond->path])
        ->and($card->script('() => document.activeElement?.dataset.slot'))->toBe('share-pick');

    $card->click('[data-slot="share-save"]')
        ->assertSee('Een link die je nu deelt, toont deze afbeelding.')
        ->assertNoJavaScriptErrors();

    expect($this->page->refresh()->share_image_id)->toBeNull();
});

it('never takes a drawing, which apps do not show with a link', function (): void {
    $logo = UploadMedia::run(new UploadedMedia(mediaFixture('logo.svg'), 'Logo.svg'));
    $card = visit('/made-cms/pages/'.$this->page->id)->resize(1280, 900);

    $card->click('[data-slot="share-pick"]');

    expect($card->script(waitUntil(pickerShows(['logo', 'training-dinsdag', 'clubavond']))))->toBeTrue();

    $card->click(pickerTile($logo))
        ->click('[data-slot="media-picker-use"]');

    expect($card->script(waitUntil(shareSlot('notices').".textContent.includes('Apps tonen geen svg')")))->toBeTrue()
        ->and($card->script('() => ['.shareSlot('value').'.value, '.shareSlot('save').'.disabled, '.shareSlot('file').'.hidden]'))->toBe(['', true, true]);

    $card->assertNoJavaScriptErrors();
});

it('shows the title and the description of the card of google, also while the editor types there', function (): void {
    $card = visit('/made-cms/pages/'.$this->page->id);

    $card->assertSeeIn('[data-slot="share-title"]', 'Over ons · TTV Merwestad')
        ->assertVisible('[data-slot="share-no-description"]')
        ->type('#made-cms-meta_title', 'Wie we zijn')
        ->type('#made-cms-meta_description', 'Een club voor iedereen.')
        ->assertSeeIn('[data-slot="share-title"]', 'Wie we zijn')
        ->assertSeeIn('[data-slot="share-description"]', 'Een club voor iedereen.')
        ->assertMissing('[data-slot="share-no-description"]')
        ->assertNoJavaScriptErrors();
});

it('keeps the shared link light in the dark colours of the system', function (): void {
    SetPageShareImage::run($this->page, $this->clubavond->id);
    $card = visit('/made-cms/pages/'.$this->page->id)->inDarkMode();

    expect($card->script(<<<'JS'
        () => {
            const colour = (selector, property) => getComputedStyle(document.querySelector(selector))[property];

            return [colour('.deellink', 'backgroundColor'), colour('.deellink-titel', 'color'), colour('.deelveld-vak', 'backgroundColor')];
        }
        JS))->toBe(['rgb(251, 250, 245)', 'rgb(14, 43, 38)', 'rgb(14, 43, 38)'])
        ->and($card->script(shareNoticeTexts()))->toBe([]);

    $card->assertNoJavaScriptErrors();
});
