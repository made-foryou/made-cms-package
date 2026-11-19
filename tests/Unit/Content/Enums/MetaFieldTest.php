<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\MetaField;

it('knows how many characters google shows of a title and of a description', function (): void {
    expect(MetaField::Title->limit())->toBe(60)
        ->and(MetaField::Description->limit())->toBe(155);
});
