<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'app.name' => 'TTV Merwestad Dordrecht']);
});

/**
 * The head of a page as the component writes it, without the whitespace around it.
 */
function metaHtml(Page $page): string
{
    return trim((string) test()->blade('<x-made-cms::meta :page="$page" />', ['page' => $page]));
}

it('writes the title of its own, the description and the canonical address of a page', function (): void {
    $page = Page::factory()->published()->withMeta('Tafeltennis in Dordrecht: onze teams', 'Van de jeugd tot de eredivisie.')->at('/teams')->create(['title' => 'Onze teams']);

    expect(metaHtml($page))->toBe(implode("\n", [
        '<title>Tafeltennis in Dordrecht: onze teams</title>',
        '<meta name="description" content="Van de jeugd tot de eredivisie.">',
        '<link rel="canonical" href="https://ttvmerwestad.nl/teams">',
        '<meta name="made-cms:page" content="Onze teams">',
    ]));
});

it('falls back to the name of the page and the site, and writes no description without one', function (): void {
    $page = Page::factory()->published()->at('/teams')->create(['title' => 'Onze teams']);

    expect(metaHtml($page))->toBe(implode("\n", [
        '<title>Onze teams · TTV Merwestad Dordrecht</title>',
        '<link rel="canonical" href="https://ttvmerwestad.nl/teams">',
        '<meta name="made-cms:page" content="Onze teams">',
    ]));
});

it('keeps a page google may not show out of the search results', function (): void {
    $page = Page::factory()->published()->noindex()->at('/bedankt')->create(['title' => 'Bedankt']);

    expect(metaHtml($page))->toContain('<meta name="robots" content="noindex">');
});

it('keeps a hidden page out of the search results', function (): void {
    $page = Page::factory()->published()->hidden()->at('/privacy')->create(['title' => 'Privacy']);

    expect(metaHtml($page))->toContain('<meta name="robots" content="noindex">');
});

it('writes no robots tag for a page google may show', function (): void {
    $page = Page::factory()->published()->at('/teams')->create();

    expect(metaHtml($page))->not->toContain('robots');
});

it('escapes the texts of the editor', function (): void {
    $page = Page::factory()->published()->withMeta('Jeugd & "senioren" <b>', 'Kom <script>alert(1)</script> & speel')->at('/jeugd')->create(['title' => 'Jeugd <i>']);

    expect(metaHtml($page))->toBe(implode("\n", [
        '<title>Jeugd &amp; &quot;senioren&quot; &lt;b&gt;</title>',
        '<meta name="description" content="Kom &lt;script&gt;alert(1)&lt;/script&gt; &amp; speel">',
        '<link rel="canonical" href="https://ttvmerwestad.nl/jeugd">',
        '<meta name="made-cms:page" content="Jeugd &lt;i&gt;">',
    ]));
});
