<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Content\Actions\MakeAttentionContext;
use Made\CmsPackage\Content\Data\AttentionContext;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

function attentionContextOf(Page ...$pages): AttentionContext
{
    return MakeAttentionContext::run(Page::query()->with('route')->whereKey(array_map(static fn (Page $page): int => $page->id, $pages))->get());
}

it('names the other pages google shows with the same title, whatever the spaces and capitals', function (): void {
    $contact = Page::factory()->published()->at('/contact')->withMeta('Contact | TTV Merwestad')->create(['title' => 'Contact']);
    $join = Page::factory()->published()->at('/lid-worden')->withMeta('  contact |  ttv merwestad ')->create(['title' => 'Lid worden']);
    $teams = Page::factory()->published()->at('/teams')->withMeta('Onze teams')->create(['title' => 'Onze teams']);

    $context = attentionContextOf($contact, $join, $teams);

    expect($context->sharingTitleWith($contact->id))->toBe(['Lid worden'])
        ->and($context->sharingTitleWith($join->id))->toBe(['Contact'])
        ->and($context->sharingTitleWith($teams->id))->toBe([]);
});

it('counts the title a page gets without one of its own', function (): void {
    $about = Page::factory()->published()->at('/over-ons')->create(['title' => 'Over ons']);
    $contact = Page::factory()->published()->at('/contact')->create(['title' => 'Contact']);
    $alsoContact = Page::factory()->published()->under($about)->at('/over-ons/contact')->create(['title' => 'Contact']);

    $context = attentionContextOf($about, $contact, $alsoContact);

    expect($context->sharingTitleWith($contact->id))->toBe(['Contact'])
        ->and($context->sharingTitleWith($alsoContact->id))->toBe(['Contact'])
        ->and($context->sharingTitleWith($about->id))->toBe([]);
});

it('names the others by name', function (): void {
    $pages = collect(['Training 10', 'Training 2', 'contact'])->map(
        static fn (string $title, int $i): Page => Page::factory()->published()->at('/pagina-'.$i)->withMeta('Dezelfde titel')->create(['title' => $title]),
    );

    expect(attentionContextOf(...$pages)->sharingTitleWith($pages[0]->id))->toBe(['contact', 'Training 2']);
});

it('leaves out a page google does not show', function (Closure $twin): void {
    $contact = Page::factory()->published()->at('/contact')->withMeta('Contact')->create(['title' => 'Contact']);
    $other = $twin();

    $context = attentionContextOf($contact, $other);

    expect($context->sharingTitleWith($contact->id))->toBe([])
        ->and($context->sharingTitleWith($other->id))->toBe([]);
})->with([
    'a draft' => [static fn (): Page => Page::factory()->at('/concept')->withMeta('Contact')->create()],
    'a hidden page' => [static fn (): Page => Page::factory()->published()->hidden()->at('/geheim')->withMeta('Contact')->create()],
    'a noindex page' => [static fn (): Page => Page::factory()->published()->noindex()->at('/intern')->withMeta('Contact')->create()],
]);

it('reads nothing itself', function (): void {
    Page::factory()->published()->at('/contact')->create();
    Page::factory()->published()->at('/lid-worden')->create();
    $pages = Page::query()->with('route')->get();

    DB::enableQueryLog();
    MakeAttentionContext::run($pages);

    expect(DB::getQueryLog())->toBe([]);
});
