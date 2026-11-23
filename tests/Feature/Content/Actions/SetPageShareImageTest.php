<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Actions\SetPageShareImage;
use Made\CmsPackage\Content\Enums\ShareImageRefusal;
use Made\CmsPackage\Content\Enums\UsageVersion;
use Made\CmsPackage\Content\Events\PageShareImageChanged;
use Made\CmsPackage\Content\Exceptions\ShareImageRefused;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

/**
 * The share image a page holds, as stored, with the file its place in the usage holds.
 *
 * @return array{column: int|null, place: int|null}
 */
function storedShareImage(Page $page): array
{
    return [
        'column' => Page::query()->findOrFail($page->id)->share_image_id,
        'place' => MediaUsage::query()->onPage($page)->where('version', UsageVersion::Page->value)->value('media_id'),
    ];
}

it('gives a page a share image of its own, with its place in the usage', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->create();

    $result = SetPageShareImage::run($page, $media->id);

    expect($result)->toBe($page)
        ->and($page->share_image_id)->toBe($media->id)
        ->and(storedShareImage($page))->toBe(['column' => $media->id, 'place' => $media->id]);
});

it('takes another image and lets go of the former', function (): void {
    [$former, $new] = Media::factory()->count(2)->create()->all();
    $page = Page::factory()->create();
    SetPageShareImage::run($page, $former->id);

    SetPageShareImage::run($page, $new->id);

    expect(storedShareImage($page))->toBe(['column' => $new->id, 'place' => $new->id])
        ->and(MediaUsage::query()->where('media_id', $former->id)->exists())->toBeFalse();
});

it('takes the share image away, so the page shows the default of the site', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->create();
    SetPageShareImage::run($page, $media->id);

    SetPageShareImage::run($page, null);

    expect(storedShareImage($page))->toBe(['column' => null, 'place' => null]);
});

it('keeps the places of the strips of the page', function (): void {
    useStripTypes();
    [$hero, $shared] = Media::factory()->count(2)->create()->all();
    $page = Page::factory()->withStrips([storedStrip('hero', ['foto' => $hero->id], id: 'h')])->create();

    SetPageShareImage::run($page, $shared->id);

    expect(MediaUsage::query()->onPage($page)->orderBy('media_id')->pluck('media_id')->all())->toBe([$hero->id, $shared->id]);
});

it('keeps the draft, the status and the texts in Google of the page', function (): void {
    $media = Media::factory()->create();
    $strips = [storedStrip(id: 'a')];
    $page = Page::factory()->published()->withMeta('Over ons', 'Wie we zijn.')->withStrips($strips)->withDraft([])->create();

    SetPageShareImage::run($page, $media->id);
    $page->refresh();

    expect($page->draft_strips)->toBe([])
        ->and($page->strips)->toBeStoredAs($strips)
        ->and($page->meta_title)->toBe('Over ons')
        ->and($page->meta_description)->toBe('Wie we zijn.');
});

it('tells once that the share image changed, after the commit', function (): void {
    Event::fake([PageShareImageChanged::class]);
    $media = Media::factory()->create();
    $page = Page::factory()->create();

    DB::transaction(function () use ($page, $media): void {
        SetPageShareImage::run($page, $media->id);

        Event::assertNotDispatched(PageShareImageChanged::class);
    });

    Event::assertDispatchedTimes(PageShareImageChanged::class, 1);
    Event::assertDispatched(PageShareImageChanged::class, fn (PageShareImageChanged $event): bool => $event->page->is($page) && $event->page->share_image_id === $media->id);
});

it('writes and tells nothing when the page already has that share image', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withShareImage($media)->create();
    Event::fake([PageShareImageChanged::class]);
    $updated = $page->updated_at;
    $this->travel(1)->minute();

    SetPageShareImage::run($page, $media->id);

    Event::assertNotDispatched(PageShareImageChanged::class);
    expect(Page::query()->findOrFail($page->id)->updated_at?->equalTo($updated))->toBeTrue();
});

it('refuses a file a shared link cannot show and keeps what the page had', function (Closure $file, ShareImageRefusal $refusal): void {
    $kept = Media::factory()->create();
    $page = Page::factory()->create();
    SetPageShareImage::run($page, $kept->id);
    Event::fake([PageShareImageChanged::class]);

    try {
        SetPageShareImage::run($page, $file());
        $this->fail('The file was taken.');
    } catch (ShareImageRefused $refused) {
        expect($refused->reason)->toBe($refusal);
    }

    Event::assertNotDispatched(PageShareImageChanged::class);
    expect(storedShareImage($page))->toBe(['column' => $kept->id, 'place' => $kept->id])
        ->and($page->share_image_id)->toBe($kept->id)
        ->and(MediaUsage::query()->count())->toBe(1);
})->with([
    'a file that is gone' => [fn (): int => missingId(), ShareImageRefusal::Gone],
    'a document' => [fn (): int => Media::factory()->document()->create()->id, ShareImageRefusal::NotAnImage],
    'a drawing' => [fn (): int => Media::factory()->create(['mime' => 'image/svg+xml'])->id, ShareImageRefusal::Drawing],
]);

it('compares with the page as it is stored, never with a model loaded before', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->create();
    SetPageShareImage::run($page, $media->id);
    $stale = Page::query()->findOrFail($page->id);
    SetPageShareImage::run($page, null);

    SetPageShareImage::run($stale, $media->id);

    expect(storedShareImage($page))->toBe(['column' => $media->id, 'place' => $media->id]);
});

it('throws for a page that was deleted after it was loaded', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->create();
    Page::query()->whereKey($page->id)->forceDelete();

    SetPageShareImage::run($page, $media->id);
})->throws(ModelNotFoundException::class);

it('locks the page first, then reads every file of it in one go in the order of their ids', function (): void {
    useStripTypes();
    [$a, $b] = Media::factory()->count(2)->create()->all();
    $page = Page::factory()->withStrips([storedStrip('hero', ['foto' => $b->id], id: 'h')])->create();
    $reads = [];

    DB::listen(static function (QueryExecuted $query) use (&$reads): void {
        if (str_starts_with($query->sql, 'select')) {
            $reads[] = $query->sql;
        }
    });

    SetPageShareImage::run($page, $a->id);

    $files = array_values(array_filter($reads, static fn (string $sql): bool => str_contains($sql, 'from '.quoted('cms_media').' ')));

    expect($reads[0])->toContain('from '.quoted('cms_pages'))
        ->and($files[0])->toContain('order by '.quoted('id').' asc')
        ->and(count($files))->toBe(2);
});

it('notes the editor who gives the page its share image', function (): void {
    $editor = Editor::factory()->create();
    $page = Page::factory()->create();

    SetPageShareImage::run($page, Media::factory()->create()->id, editorId: $editor->id);

    expect(Page::query()->findOrFail($page->id)->updated_by)->toBe($editor->id);
});

it('leaves who changed the page last when the page already has that share image', function (): void {
    $before = Editor::factory()->create();
    $media = Media::factory()->create();
    $page = Page::factory()->withShareImage($media)->create(['updated_by' => $before->id]);

    SetPageShareImage::run($page, $media->id, editorId: Editor::factory()->create()->id);

    expect(Page::query()->findOrFail($page->id)->updated_by)->toBe($before->id);
});
