<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Screen;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Http\Cms\AttentionActions;

it('points the meta of Google at its card on the screen of the page', function (AttentionReason $reason): void {
    $page = Page::factory()->create();

    expect(AttentionActions::url($reason, $page, []))->toBe('#made-cms-google');
})->with([AttentionReason::MetaDescriptionMissing, AttentionReason::MetaTitleTooLong]);

it('points the content of a page at the edit shell for an editor who opens the pages', function (AttentionReason $reason): void {
    $page = Page::factory()->create();

    expect(AttentionActions::url($reason, $page, [Screen::Pages]))->toBe('/made-cms/pages/'.$page->id.'/edit');
})->with([AttentionReason::StaleDraft, AttentionReason::Empty]);

it('gives no way to a screen the editor does not open', function (AttentionReason $reason): void {
    $page = Page::factory()->create();

    expect(AttentionActions::url($reason, $page, [Screen::MetaTags, Screen::Media]))->toBeNull();
})->with([AttentionReason::StaleDraft, AttentionReason::Empty]);
