<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\PageStatus;

it('stores the status as a string value', function (PageStatus $status, string $value): void {
    expect($status->value)->toBe($value);
})->with([
    [PageStatus::Draft, 'draft'],
    [PageStatus::Published, 'published'],
]);

it('labels the status in Dutch', function (PageStatus $status, string $label): void {
    expect($status->label())->toBe($label);
})->with([
    [PageStatus::Draft, 'Concept'],
    [PageStatus::Published, 'Live'],
]);

it('labels the status in English', function (): void {
    app()->setLocale('en');

    expect(PageStatus::Draft->label())->toBe('Draft');
});
