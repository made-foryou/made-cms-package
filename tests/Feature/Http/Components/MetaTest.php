<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Content\Models\Media;
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
        '<meta property="og:type" content="website">',
        '<meta property="og:site_name" content="TTV Merwestad Dordrecht">',
        '<meta property="og:title" content="Tafeltennis in Dordrecht: onze teams">',
        '<meta property="og:description" content="Van de jeugd tot de eredivisie.">',
        '<meta property="og:url" content="https://ttvmerwestad.nl/teams">',
        '<meta name="twitter:card" content="summary">',
        '<meta name="made-cms:page" content="Onze teams">',
    ]));
});

it('falls back to the name of the page and the site, and writes no description without one', function (): void {
    $page = Page::factory()->published()->at('/teams')->create(['title' => 'Onze teams']);

    expect(metaHtml($page))->toBe(implode("\n", [
        '<title>Onze teams · TTV Merwestad Dordrecht</title>',
        '<link rel="canonical" href="https://ttvmerwestad.nl/teams">',
        '<meta property="og:type" content="website">',
        '<meta property="og:site_name" content="TTV Merwestad Dordrecht">',
        '<meta property="og:title" content="Onze teams · TTV Merwestad Dordrecht">',
        '<meta property="og:url" content="https://ttvmerwestad.nl/teams">',
        '<meta name="twitter:card" content="summary">',
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
        '<meta property="og:type" content="website">',
        '<meta property="og:site_name" content="TTV Merwestad Dordrecht">',
        '<meta property="og:title" content="Jeugd &amp; &quot;senioren&quot; &lt;b&gt;">',
        '<meta property="og:description" content="Kom &lt;script&gt;alert(1)&lt;/script&gt; &amp; speel">',
        '<meta property="og:url" content="https://ttvmerwestad.nl/jeugd">',
        '<meta name="twitter:card" content="summary">',
        '<meta name="made-cms:page" content="Jeugd &lt;i&gt;">',
    ]));
});

describe('sharing a link', function (): void {
    beforeEach(function (): void {
        Storage::fake('public', ['url' => 'https://ttvmerwestad.nl/storage']);
    });

    it('writes the share image of the page with its size and alt text, and a large card', function (): void {
        $media = Media::factory()->withAlt('Spelers van Merwestad 1 na de winst')->create(['width' => 1200, 'height' => 630]);
        $page = Page::factory()->published()->withShareImage($media)->at('/teams')->create(['title' => 'Onze teams']);

        expect(metaHtml($page))->toBe(implode("\n", [
            '<title>Onze teams · TTV Merwestad Dordrecht</title>',
            '<link rel="canonical" href="https://ttvmerwestad.nl/teams">',
            '<meta property="og:type" content="website">',
            '<meta property="og:site_name" content="TTV Merwestad Dordrecht">',
            '<meta property="og:title" content="Onze teams · TTV Merwestad Dordrecht">',
            '<meta property="og:url" content="https://ttvmerwestad.nl/teams">',
            '<meta property="og:image" content="https://ttvmerwestad.nl/storage/'.$media->path.'">',
            '<meta property="og:image:width" content="1200">',
            '<meta property="og:image:height" content="630">',
            '<meta property="og:image:alt" content="Spelers van Merwestad 1 na de winst">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="made-cms:page" content="Onze teams">',
        ]));
    });

    it('writes no alt text for an image without one, and no size it does not know', function (): void {
        $media = Media::factory()->create(['width' => null, 'height' => 630]);
        $page = Page::factory()->published()->withShareImage($media)->at('/teams')->create();

        expect(metaHtml($page))->toContain('<meta property="og:image" content="https://ttvmerwestad.nl/storage/'.$media->path.'">')
            ->not->toContain('og:image:width')
            ->not->toContain('og:image:height')
            ->not->toContain('og:image:alt');
    });

    it('writes the default image of the site for a page without one of its own', function (): void {
        config(['made-cms-package.meta.share_image' => ['url' => '/images/deel.jpg', 'width' => 1200, 'height' => 630, 'alt' => 'Het clubhuis']]);
        $page = Page::factory()->published()->at('/teams')->create();

        expect(metaHtml($page))->toContain(implode("\n", [
            '<meta property="og:image" content="https://ttvmerwestad.nl/images/deel.jpg">',
            '<meta property="og:image:width" content="1200">',
            '<meta property="og:image:height" content="630">',
            '<meta property="og:image:alt" content="Het clubhuis">',
            '<meta name="twitter:card" content="summary_large_image">',
        ]));
    });

    it('gives a page google may not show the tags for sharing too', function (bool $hidden): void {
        $media = Media::factory()->create();
        $page = Page::factory()->published()->noindex()->withShareImage($media)->at('/bedankt')->create(['hidden' => $hidden]);

        expect(metaHtml($page))->toContain('<meta name="robots" content="noindex">')
            ->toContain('<meta property="og:title"')
            ->toContain('<meta property="og:image"')
            ->toContain('<meta name="twitter:card" content="summary_large_image">');
    })->with(['noindex' => false, 'hidden' => true]);

    it('writes no address to share for a page without an address', function (): void {
        $page = Page::factory()->create();

        expect(metaHtml($page))->not->toContain('og:url');
    });

    it('escapes the alt text of the file', function (): void {
        $media = Media::factory()->withAlt('Winst & "feest" <b>')->create();
        $page = Page::factory()->published()->withShareImage($media)->at('/teams')->create();

        expect(metaHtml($page))->toContain('<meta property="og:image:alt" content="Winst &amp; &quot;feest&quot; &lt;b&gt;">');
    });

    it('keeps the name of the page last, where the edit bar reads it', function (): void {
        $media = Media::factory()->create();
        $page = Page::factory()->published()->withShareImage($media)->at('/teams')->create(['title' => 'Onze teams']);

        expect(metaHtml($page))->toEndWith('<meta name="made-cms:page" content="Onze teams">');
    });
});
