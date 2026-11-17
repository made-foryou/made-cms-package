<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\DecideEditExit;
use Made\CmsPackage\Content\Data\EditExitSituation;
use Made\CmsPackage\Content\Enums\EditExit;
use Made\CmsPackage\Content\Enums\PageStatus;

it('sends the editor to the site when visitors see the page', function (): void {
    $situation = new EditExitSituation(status: PageStatus::Published, hasAddress: true, publishing: false);

    expect(DecideEditExit::run($situation))->toBe(EditExit::Site);
});

it('sends the editor to the pages screen when the page was never published', function (): void {
    $situation = new EditExitSituation(status: PageStatus::Draft, hasAddress: true, publishing: false);

    expect(DecideEditExit::run($situation))->toBe(EditExit::Pages);
});

it('sends the editor to the site after publishing a page that was never published', function (): void {
    $situation = new EditExitSituation(status: PageStatus::Draft, hasAddress: true, publishing: true);

    expect(DecideEditExit::run($situation))->toBe(EditExit::Site);
});

it('sends the editor to the pages screen when the page has no address', function (bool $publishing, PageStatus $status): void {
    $situation = new EditExitSituation(status: $status, hasAddress: false, publishing: $publishing);

    expect(DecideEditExit::run($situation))->toBe(EditExit::Pages);
})->with([
    'published' => [false, PageStatus::Published],
    'publishing a draft' => [true, PageStatus::Draft],
]);
