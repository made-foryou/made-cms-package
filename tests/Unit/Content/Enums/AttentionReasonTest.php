<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\AttentionReason;

it('lets a page stand out in a list for every reason but being empty, which the page itself only points out', function (AttentionReason $reason): void {
    expect($reason->showsInLists())->toBe($reason !== AttentionReason::Empty);
})->with(AttentionReason::cases());
