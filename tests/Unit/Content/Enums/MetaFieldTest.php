<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\MetaField;

it('knows how many characters google shows of a title and of a description', function (): void {
    expect(MetaField::Title->limit())->toBe(60)
        ->and(MetaField::Description->limit())->toBe(155);
});

it('warns above nine tenths of what google shows, in whole characters', function (): void {
    expect(MetaField::Title->warnAbove())->toBe(54)
        ->and(MetaField::Description->warnAbove())->toBe(139);
});

it('takes at most about twice what google shows', function (): void {
    expect(MetaField::Title->maxLength())->toBe(120)
        ->and(MetaField::Description->maxLength())->toBe(320);
});
