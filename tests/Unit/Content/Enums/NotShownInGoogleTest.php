<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\NotShownInGoogle;
use Made\CmsPackage\Content\Enums\PageStatus;

it('has no reason for a published page google may show', function (): void {
    expect(NotShownInGoogle::of(PageStatus::Published, hidden: false, indexable: true))->toBeNull();
});

it('says why google does not show a page, the draft first and then the hidden page', function (PageStatus $status, bool $hidden, bool $indexable, NotShownInGoogle $reason): void {
    expect(NotShownInGoogle::of($status, $hidden, $indexable))->toBe($reason);
})->with([
    'a draft' => [PageStatus::Draft, false, true, NotShownInGoogle::Draft],
    'a hidden noindex draft' => [PageStatus::Draft, true, false, NotShownInGoogle::Draft],
    'a hidden page' => [PageStatus::Published, true, true, NotShownInGoogle::Hidden],
    'a hidden noindex page' => [PageStatus::Published, true, false, NotShownInGoogle::Hidden],
    'a noindex page' => [PageStatus::Published, false, false, NotShownInGoogle::Noindex],
]);
