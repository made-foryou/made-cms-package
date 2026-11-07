<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\ListPages;
use Made\CmsPackage\Content\Actions\SetStripVisibility;
use Made\CmsPackage\Content\Data\PageNode;
use Made\CmsPackage\Content\Enums\PageStatus;
use Made\CmsPackage\Content\Models\Page;

it('lists the pages at the top in menu order with the pages under them', function (): void {
    $contact = Page::factory()->atPosition(2)->at('/contact')->create();
    $home = Page::factory()->atPosition(0)->at('/')->create();
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create();
    $history = Page::factory()->under($about)->atPosition(1)->at('/over-ons/geschiedenis')->create();
    $team = Page::factory()->under($about)->atPosition(0)->at('/over-ons/team')->create();

    $tree = ListPages::run();

    expect(array_map(fn (PageNode $node): int => $node->id, $tree->roots))->toBe([$home->id, $about->id, $contact->id])
        ->and(array_map(fn (PageNode $node): int => $node->id, $tree->flatten()))->toBe([$home->id, $about->id, $team->id, $history->id, $contact->id]);
});

it('carries what the screen shows of every page', function (): void {
    $about = Page::factory()->published()->at('/over-ons')->withStrips([storedStrip()])->withDraft([])->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    Page::factory()->under($about)->inMenu(false)->atPosition(3)->at('/over-ons/team')->create(['title' => 'Team', 'slug' => 'team']);

    [$parent, $child] = ListPages::run()->flatten();

    expect($parent->title)->toBe('Over ons')
        ->and($parent->path?->value)->toBe('/over-ons')
        ->and($parent->status)->toBe(PageStatus::Published)
        ->and($parent->hasDraft)->toBeTrue()
        ->and($parent->isEmpty)->toBeFalse()
        ->and($parent->depth)->toBe(0)
        ->and($child->parentId)->toBe($about->id)
        ->and($child->depth)->toBe(1)
        ->and($child->position)->toBe(3)
        ->and($child->inMenu)->toBeFalse()
        ->and($child->hasDraft)->toBeFalse()
        ->and($child->isEmpty)->toBeTrue();
});

it('lists no draft once its change is taken back', function (): void {
    $page = Page::factory()->published()->withStrips([storedStrip(id: 'a')])->create();

    SetStripVisibility::run($page, 'a', false);

    expect(ListPages::run()->flatten()[0]->hasDraft ?? null)->toBeTrue();

    SetStripVisibility::run($page, 'a', true);

    expect(ListPages::run()->flatten()[0]->hasDraft ?? null)->toBeFalse();
});

it('includes a page without an address', function (): void {
    Page::factory()->create();

    expect(ListPages::run()->flatten()[0]->path)->toBeNull();
});

it('leaves out deleted pages', function (): void {
    Page::factory()->trashed()->create();

    expect(ListPages::run()->isEmpty())->toBeTrue();
});

it('gives an empty tree without pages', function (): void {
    expect(ListPages::run()->roots)->toBe([]);
});
