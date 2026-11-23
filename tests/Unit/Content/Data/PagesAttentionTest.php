<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Data\PageAttention;
use Made\CmsPackage\Content\Data\PagesAttention;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;

function attentionOf(AttentionReason ...$reasons): PageAttention
{
    return new PageAttention(array_map(
        static fn (AttentionReason $reason): AttentionItem => new AttentionItem($reason, $reason === AttentionReason::MetaTitleTooLong ? AttentionSeverity::Medium : AttentionSeverity::High),
        array_values($reasons),
    ));
}

it('gives what a page asks for attention by its id, and nothing for a page it does not know', function (): void {
    $pages = new PagesAttention([3 => attentionOf(AttentionReason::MetaTitleTooLong)]);

    expect($pages->of(3)->count())->toBe(1)
        ->and($pages->of(4)->isEmpty())->toBeTrue();
});

it('counts the pages that ask for attention', function (): void {
    $pages = new PagesAttention([1 => attentionOf(AttentionReason::MetaTitleTooLong), 2 => attentionOf(), 3 => attentionOf(AttentionReason::StaleDraft)]);

    expect($pages->flagged())->toBe(2);
});

it('gives every reason of its pages once, the urgent first and then in the order of the reasons', function (): void {
    $pages = new PagesAttention([
        1 => attentionOf(AttentionReason::MetaTitleTooLong),
        2 => attentionOf(AttentionReason::MetaDescriptionMissing),
        3 => attentionOf(AttentionReason::StaleDraft, AttentionReason::MetaDescriptionMissing),
    ]);

    expect($pages->reasons())->toBe([AttentionReason::StaleDraft, AttentionReason::MetaDescriptionMissing, AttentionReason::MetaTitleTooLong])
        ->and((new PagesAttention([]))->reasons())->toBe([]);
});
