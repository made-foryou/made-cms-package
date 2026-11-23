<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Data\PageAttention;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;

it('keeps for a list only the items that let a page stand out there, in their order', function (): void {
    $attention = new PageAttention([
        new AttentionItem(AttentionReason::MetaDescriptionMissing, AttentionSeverity::High),
        new AttentionItem(AttentionReason::Empty, AttentionSeverity::Medium),
        new AttentionItem(AttentionReason::MetaTitleTooLong, AttentionSeverity::Medium, ['length' => 68, 'limit' => 60]),
    ]);

    expect(array_map(static fn (AttentionItem $item): AttentionReason => $item->reason, $attention->inLists()->items))
        ->toBe([AttentionReason::MetaDescriptionMissing, AttentionReason::MetaTitleTooLong]);
});

it('leaves nothing for a list of a page that is only empty', function (): void {
    $attention = new PageAttention([new AttentionItem(AttentionReason::Empty, AttentionSeverity::Medium)]);

    expect($attention->inLists()->isEmpty())->toBeTrue();
});
