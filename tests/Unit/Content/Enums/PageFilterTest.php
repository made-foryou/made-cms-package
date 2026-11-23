<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Data\PageAttention;
use Made\CmsPackage\Content\Data\PagesAttention;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Enums\AttentionSeverity;
use Made\CmsPackage\Content\Enums\PageFilter;
use Made\CmsPackage\Content\Enums\PageStatus;

function noAttention(): PagesAttention
{
    return new PagesAttention([]);
}

it('matches every page for all of them', function (): void {
    expect(PageFilter::All->matches(pageNode(1, '/jeugd'), noAttention()))->toBeTrue();
});

it('matches a page visitors see as live: published and not hidden, with or without a draft', function (): void {
    expect(PageFilter::Live->matches(pageNode(1, '/', status: PageStatus::Published), noAttention()))->toBeTrue()
        ->and(PageFilter::Live->matches(pageNode(2, '/over-ons', status: PageStatus::Published, hasDraft: true), noAttention()))->toBeTrue()
        ->and(PageFilter::Live->matches(pageNode(3, '/privacy', status: PageStatus::Published, hidden: true), noAttention()))->toBeFalse()
        ->and(PageFilter::Live->matches(pageNode(4, '/jeugd'), noAttention()))->toBeFalse();
});

it('matches a page with something to publish as a draft: never published, or a draft with changes', function (): void {
    expect(PageFilter::Draft->matches(pageNode(1, '/jeugd'), noAttention()))->toBeTrue()
        ->and(PageFilter::Draft->matches(pageNode(2, '/over-ons', status: PageStatus::Published, hasDraft: true), noAttention()))->toBeTrue()
        ->and(PageFilter::Draft->matches(pageNode(3, '/', status: PageStatus::Published), noAttention()))->toBeFalse();
});

it('matches a page that asks for attention in a list', function (): void {
    $attention = new PagesAttention([1 => new PageAttention([new AttentionItem(AttentionReason::MetaDescriptionMissing, AttentionSeverity::High)])]);

    expect(PageFilter::Attention->matches(pageNode(1, '/teams', status: PageStatus::Published), $attention))->toBeTrue()
        ->and(PageFilter::Attention->matches(pageNode(2, '/', status: PageStatus::Published), $attention))->toBeFalse();
});

it('matches a page whose switch hides it from visitors, also a hidden draft, which is a draft too', function (): void {
    $hiddenDraft = pageNode(1, '/bedankt', hidden: true);

    expect(PageFilter::Hidden->matches($hiddenDraft, noAttention()))->toBeTrue()
        ->and(PageFilter::Draft->matches($hiddenDraft, noAttention()))->toBeTrue()
        ->and(PageFilter::Hidden->matches(pageNode(2, '/', status: PageStatus::Published), noAttention()))->toBeFalse();
});
