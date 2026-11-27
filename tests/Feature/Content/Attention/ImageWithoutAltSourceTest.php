<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Attention\ImageWithoutAltSource;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
});

/**
 * A page with these strips published and, when given, these in its draft, with the places of its files stored.
 *
 * @param  list<StoredStrip>  $published
 * @param  list<StoredStrip>|null  $draft
 */
function pageWithFiles(array $published, ?array $draft = null, ?Media $share = null): Page
{
    $factory = Page::factory()->published()->withStrips($published);
    $factory = $draft === null ? $factory : $factory->withDraft($draft);
    $page = ($share === null ? $factory : $factory->withShareImage($share))->create();
    SyncMediaUsage::run($page);

    return $page;
}

it('asks for the alt text of a photo on the working copy', function (): void {
    $photo = Media::factory()->create();
    $page = pageWithFiles([storedStrip('hero', ['foto' => $photo->id])]);

    expect((new ImageWithoutAltSource)->items($page, new AttentionContext))->toEqual([
        new AttentionItem(AttentionReason::ImageWithoutAlt, AttentionSeverity::Medium, ['count' => 1, 'media' => $photo->id]),
    ]);
});

it('counts every photo without an alt text once and gives the one the page shows first', function (): void {
    [$first, $second] = Media::factory()->count(2)->create()->all();
    $page = pageWithFiles([], [
        storedStrip('hero', ['foto' => $second->id], id: 'h1'),
        storedStrip('cards', ['kaarten' => [['id' => 'k1', 'icoon' => $first->id], ['id' => 'k2', 'icoon' => $second->id]]], id: 'c'),
        storedStrip('hero', ['foto' => $first->id], visible: false, id: 'h2'),
    ]);

    expect((new ImageWithoutAltSource)->items($page, new AttentionContext))->toEqual([
        new AttentionItem(AttentionReason::ImageWithoutAlt, AttentionSeverity::Medium, ['count' => 2, 'media' => $second->id]),
    ]);
});

it('leaves a page alone whose files have an alt text or need none', function (Closure $media): void {
    $page = pageWithFiles([storedStrip('hero', ['foto' => $media()->id])]);

    expect((new ImageWithoutAltSource)->items($page, new AttentionContext))->toBe([]);
})->with([
    'a photo with an alt text' => [static fn (): Media => Media::factory()->withAlt('Het eerste team')->create()],
    'a document' => [static fn (): Media => Media::factory()->document()->create()],
    'a video' => [static fn (): Media => Media::factory()->video()->create()],
]);

it('looks only at the working copy: a photo the draft took away no longer asks, one it added does', function (): void {
    $photo = Media::factory()->create();
    $described = Media::factory()->withAlt('Het eerste team')->create();

    $removed = pageWithFiles([storedStrip('hero', ['foto' => $photo->id], id: 'h')], [storedStrip('hero', ['foto' => $described->id], id: 'h')]);
    $added = pageWithFiles([storedStrip('hero', ['foto' => $described->id], id: 'h')], [storedStrip('hero', ['foto' => $photo->id], id: 'h')]);

    expect((new ImageWithoutAltSource)->items($removed, new AttentionContext))->toBe([])
        ->and((new ImageWithoutAltSource)->items($added, new AttentionContext))->toEqual([
            new AttentionItem(AttentionReason::ImageWithoutAlt, AttentionSeverity::Medium, ['count' => 1, 'media' => $photo->id]),
        ]);
});

it('leaves the share image alone, which its own card asks the alt text of', function (): void {
    $page = pageWithFiles([storedStrip('hero', ['kop' => 'Welkom'])], share: Media::factory()->create());

    expect((new ImageWithoutAltSource)->items($page, new AttentionContext))->toBe([]);
});
