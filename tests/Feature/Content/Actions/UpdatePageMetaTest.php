<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\UpdatePageMeta;
use Made\CmsPackage\Content\Data\PageMetaChanges;
use Made\CmsPackage\Content\Events\PageMetaUpdated;
use Made\CmsPackage\Content\Models\Page;

it('stores the title and the description in google and both switches', function (): void {
    $page = Page::factory()->at('/teams')->create();

    UpdatePageMeta::run($page, new PageMetaChanges(
        title: 'Onze 14 teams · TTV Merwestad',
        description: 'Van de jeugd tot de eredivisie.',
        inSitemap: false,
        indexable: false,
    ));

    $page->refresh();

    expect($page->meta_title)->toBe('Onze 14 teams · TTV Merwestad')
        ->and($page->meta_description)->toBe('Van de jeugd tot de eredivisie.')
        ->and($page->in_sitemap)->toBeFalse()
        ->and($page->indexable)->toBeFalse();
});

it('stores the texts on one line without the spaces around them', function (): void {
    $page = Page::factory()->create();

    UpdatePageMeta::run($page, new PageMetaChanges(
        title: "  Onze   teams\n",
        description: "Van de jeugd\r\ntot de  eredivisie. ",
        inSitemap: true,
        indexable: true,
    ));

    expect($page->refresh()->meta_title)->toBe('Onze teams')
        ->and($page->meta_description)->toBe('Van de jeugd tot de eredivisie.');
});

it('stores a blank text as none, so the page falls back again', function (): void {
    $page = Page::factory()->withMeta('Onze teams', 'Van de jeugd tot de eredivisie.')->create();

    UpdatePageMeta::run($page, new PageMetaChanges(title: '  ', description: '', inSitemap: true, indexable: true));

    expect($page->refresh()->meta_title)->toBeNull()
        ->and($page->meta_description)->toBeNull();
});

it('tells once that the google fields of a page changed, after the commit', function (): void {
    Event::fake([PageMetaUpdated::class]);
    $page = Page::factory()->create();

    DB::transaction(function () use ($page): void {
        UpdatePageMeta::run($page, new PageMetaChanges(title: 'Onze teams', description: null, inSitemap: true, indexable: false));

        Event::assertNotDispatched(PageMetaUpdated::class);
    });

    Event::assertDispatchedTimes(PageMetaUpdated::class, 1);
    Event::assertDispatched(PageMetaUpdated::class, fn (PageMetaUpdated $event): bool => $event->page->is($page));
});

it('tells nothing when nothing changed', function (): void {
    Event::fake([PageMetaUpdated::class]);
    $page = Page::factory()->withMeta('Onze teams')->create();

    UpdatePageMeta::run($page, new PageMetaChanges(title: ' Onze teams ', description: null, inSitemap: true, indexable: true));

    Event::assertNotDispatched(PageMetaUpdated::class);
});

it('notes the editor who changes the google fields', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->create();

    UpdatePageMeta::run($page, new PageMetaChanges(title: 'Onze teams', description: null, inSitemap: true, indexable: true, editorId: $editor->id));

    expect($page->refresh()->updated_by)->toBe($editor->id);
});

it('leaves who changed the page last when nothing changed', function (): void {
    $before = Editor::factory()->create();
    $page = Page::factory()->withMeta('Onze teams')->create(['updated_by' => $before->id]);

    UpdatePageMeta::run($page, new PageMetaChanges(title: 'Onze teams', description: null, inSitemap: true, indexable: true, editorId: Editor::factory()->create()->id));

    expect($page->refresh()->updated_by)->toBe($before->id);
});

it('keeps both switches when the change leaves them out', function (bool $on): void {
    $page = Page::factory()->at('/teams')->create(['in_sitemap' => $on, 'indexable' => $on]);

    UpdatePageMeta::run($page, new PageMetaChanges(title: 'Onze teams', description: 'Alle teams van de club.'));

    $page->refresh();

    expect($page->meta_title)->toBe('Onze teams')
        ->and($page->meta_description)->toBe('Alle teams van de club.')
        ->and($page->in_sitemap)->toBe($on)
        ->and($page->indexable)->toBe($on);
})->with([true, false]);

it('tells nothing when only the texts it was given are what the page holds', function (): void {
    $page = Page::factory()->at('/teams')->withMeta('Onze teams', 'Alle teams van de club.')->create();
    Event::fake([PageMetaUpdated::class]);

    UpdatePageMeta::run($page, new PageMetaChanges(title: 'Onze teams', description: 'Alle teams van de club.'));

    Event::assertNotDispatched(PageMetaUpdated::class);
});

it('changes only whether the sitemap lists a page, and keeps its texts and whether Google may show it', function (): void {
    Event::fake([PageMetaUpdated::class]);
    $editor = Editor::factory()->create();
    $page = Page::factory()->at('/privacy')->withMeta('Privacy · TTV Merwestad', 'Hoe we met je gegevens omgaan.')->noindex()->create();

    UpdatePageMeta::run($page, PageMetaChanges::sitemap(false, $editor->id));

    $page->refresh();

    expect($page->in_sitemap)->toBeFalse()
        ->and($page->meta_title)->toBe('Privacy · TTV Merwestad')
        ->and($page->meta_description)->toBe('Hoe we met je gegevens omgaan.')
        ->and($page->indexable)->toBeFalse()
        ->and($page->updated_by)->toBe($editor->id);
    Event::assertDispatchedTimes(PageMetaUpdated::class, 1);
});

it('tells nothing when the sitemap lists the page as it was asked to', function (): void {
    Event::fake([PageMetaUpdated::class]);
    $page = Page::factory()->at('/teams')->withMeta('Teams', null)->create();

    UpdatePageMeta::run($page, PageMetaChanges::sitemap(true));

    expect($page->refresh()->meta_title)->toBe('Teams');
    Event::assertNotDispatched(PageMetaUpdated::class);
});
