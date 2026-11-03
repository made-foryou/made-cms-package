<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\EditMode;

it('is off for a visitor and on for an editor in the edit mode', function (): void {
    expect(EditMode::off()->active)->toBeFalse()
        ->and(EditMode::on()->active)->toBeTrue();
});
