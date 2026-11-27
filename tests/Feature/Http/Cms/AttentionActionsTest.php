<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\AttentionActions;

/**
 * @param  array<string, int|string>  $details
 */
function attentionItem(AttentionReason $reason, array $details = []): AttentionItem
{
    return new AttentionItem($reason, AttentionSeverity::Medium, $details);
}

it('points the meta of Google at its card on the screen of the page', function (AttentionReason $reason): void {
    $page = Page::factory()->create();

    expect(AttentionActions::url(attentionItem($reason), $page, []))->toBe('#made-cms-google');
})->with([AttentionReason::MetaDescriptionMissing, AttentionReason::MetaTitleTooLong, AttentionReason::MetaDescriptionTooLong]);

it('points the content of a page at the edit shell for an editor who opens the pages', function (AttentionReason $reason): void {
    $page = Page::factory()->create();

    expect(AttentionActions::url(attentionItem($reason), $page, [Screen::Pages]))->toBe('/made-cms/pages/'.$page->id.'/edit');
})->with([AttentionReason::StaleDraft, AttentionReason::Empty, AttentionReason::PublicationFailed]);

it('points a planned publication that is late at the basics, where it is published at once', function (): void {
    $page = Page::factory()->create();

    expect(AttentionActions::url(attentionItem(AttentionReason::PublicationOverdue), $page, []))->toBe('#made-cms-basis');
});

it('points a photo without an alt text at Media with that photo chosen, more photos at those without one', function (): void {
    $page = Page::factory()->create();

    expect(AttentionActions::url(attentionItem(AttentionReason::ImageWithoutAlt, ['count' => 1, 'media' => 7]), $page, [Screen::Media]))
        ->toBe('/made-cms/media?file=7')
        ->and(AttentionActions::url(attentionItem(AttentionReason::ImageWithoutAlt, ['count' => 3, 'media' => 7]), $page, [Screen::Media]))
        ->toBe('/made-cms/media?without_alt=1');
});

it('points more than one h1 at the overview Koppen of the edit shell for an editor who chooses the level of a heading', function (): void {
    $page = Page::factory()->create();

    expect(AttentionActions::url(attentionItem(AttentionReason::ManyH1, ['count' => 2]), $page, [Screen::Pages, Screen::MetaTags]))
        ->toBe('/made-cms/pages/'.$page->id.'/edit?view=headings');
});

it('gives no way to a screen the editor does not open', function (AttentionReason $reason, array $screens): void {
    $page = Page::factory()->create();

    expect(AttentionActions::url(attentionItem($reason, ['count' => 1, 'media' => 7]), $page, $screens))->toBeNull();
})->with([
    [AttentionReason::StaleDraft, [Screen::MetaTags, Screen::Media]],
    [AttentionReason::Empty, [Screen::MetaTags, Screen::Media]],
    [AttentionReason::PublicationFailed, [Screen::MetaTags, Screen::Media]],
    [AttentionReason::ImageWithoutAlt, [Screen::Pages, Screen::AltTexts]],
    [AttentionReason::ManyH1, [Screen::Pages, Screen::Media]],
]);
