<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\PageState;
use Made\CmsPackage\Content\Enums\PageStatus;

it('names a published page visitors see live', function (): void {
    expect(PageState::of(PageStatus::Published, hidden: false))->toBe(PageState::Live);
});

it('names a published page visitors only reach through its address hidden', function (): void {
    expect(PageState::of(PageStatus::Published, hidden: true))->toBe(PageState::Hidden);
});

it('names a page that was never published a draft, hidden or not, because no address reaches it yet', function (bool $hidden): void {
    expect(PageState::of(PageStatus::Draft, $hidden))->toBe(PageState::Draft);
})->with([false, true]);
