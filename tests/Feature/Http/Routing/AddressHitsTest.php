<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\AddressHit;
use Made\CmsPackage\Content\Models\NotFoundPath;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Sitemap;

beforeEach(function (): void {
    config(['app.url' => 'https://ttvmerwestad.nl', 'made-cms-package.sitemap.robots' => []]);
    $this->travelTo(CarbonImmutable::parse('2026-11-28 10:00:00'));
});

/**
 * Every count as "prefix path outcome source referrer hits", in the order it was made.
 *
 * @return list<string>
 */
function counted(): array
{
    return AddressHit::query()->orderBy('id')->get()->map(static fn (AddressHit $hit): string => trim(implode(' ', [
        $hit->prefix === '' ? '-' : $hit->prefix,
        $hit->path,
        $hit->outcome->value,
        $hit->source->value,
        $hit->referrer_path,
    ])).' '.$hit->hits)->all();
}

it('counts a visitor at an address without a page, where they came from and the dead door', function (): void {
    servePages();

    $this->withHeaders(['User-Agent' => aBrowser(), 'Referer' => 'https://www.google.nl/'])
        ->get('lid-worden-oud')
        ->assertNotFound();

    expect(counted())->toBe(['- /lid-worden-oud not_found google 1'])
        ->and(NotFoundPath::query()->pluck('path')->all())->toBe(['/lid-worden-oud']);
});

it('counts a visitor at a draft as a dead door', function (): void {
    Page::factory()->at('/jeugd')->create();
    servePages();

    $this->withHeaders(['User-Agent' => aBrowser()])->get('jeugd')->assertNotFound();

    expect(counted())->toBe(['- /jeugd not_found direct 1']);
});

it('names the page of the site that linked to a dead door', function (): void {
    Page::factory()->published()->at('/trainingstijden')->create();
    servePages();

    $this->withHeaders(['User-Agent' => aBrowser(), 'Referer' => 'https://ttvmerwestad.nl/trainingstijden'])
        ->get('lid-worden-oud')
        ->assertNotFound();

    expect(counted())->toBe(['- /lid-worden-oud not_found internal /trainingstijden 1']);
});

it('counts a page for Googlebot and another crawler, never for a person', function (): void {
    Page::factory()->published()->at('/teams')->create();
    servePages();

    $this->withHeaders(['User-Agent' => googlebot()])->get('teams')->assertOk();
    $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; bingbot/2.0)'])->get('teams')->assertOk();
    $this->withHeaders(['User-Agent' => aBrowser()])->get('teams')->assertOk();

    expect(counted())->toBe(['- /teams page googlebot 1', '- /teams page bot 1']);
});

it('counts nothing for an editor in the edit mode or the preview', function (string $mode): void {
    Page::factory()->at('/jeugd')->create();
    Page::factory()->published()->at('/teams')->create();
    servePages();

    $this->actingAs(Editor::factory()->create(), 'made-cms')->withHeaders(['User-Agent' => googlebot()]);

    $this->get('jeugd?made-cms='.$mode)->assertOk();
    $this->get('teams?made-cms='.$mode)->assertOk();
    $this->get('bestaat-niet?made-cms='.$mode)->assertNotFound();

    expect(counted())->toBe([])
        ->and(NotFoundPath::query()->exists())->toBeFalse();
})->with(['edit', 'preview']);

it('counts anyone else who asks for the edit mode as a visitor', function (): void {
    Page::factory()->at('/jeugd')->create();
    servePages();

    $this->withHeaders(['User-Agent' => aBrowser()])->get('jeugd?made-cms=edit')->assertNotFound();

    expect(counted())->toBe(['- /jeugd not_found direct 1']);
});

it('counts nothing for an address that can be no address', function (): void {
    servePages();
    $this->withoutMiddleware(ValidatePathEncoding::class);

    $this->get('over%FFons')->assertNotFound();
    $this->get('over%00ons')->assertNotFound();

    expect(counted())->toBe([]);
});

it('counts the page once its address is the one of the page, not the redirect to it', function (): void {
    Page::factory()->published()->at('/over-ons')->create();
    servePages();

    $this->withHeaders(['User-Agent' => googlebot()])->get('Over-Ons')->assertRedirect('/over-ons');

    expect(counted())->toBe([]);

    $this->withHeaders(['User-Agent' => googlebot()])->get('over-ons')->assertOk();

    expect(counted())->toBe(['- /over-ons page googlebot 1']);
});

it('counts an address within its route group', function (): void {
    Page::factory()->published()->at('/teams')->create();
    Route::middleware('web')->prefix('nl')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });

    $this->withHeaders(['User-Agent' => googlebot()])->get('nl/teams')->assertOk();
    $this->withHeaders(['User-Agent' => aBrowser()])->get('nl/lid-worden-oud')->assertNotFound();

    expect(counted())->toBe(['nl /teams page googlebot 1', 'nl /lid-worden-oud not_found direct 1'])
        ->and(NotFoundPath::query()->pluck('path')->all())->toBe(['/lid-worden-oud']);
});

it('counts the sitemap and robots.txt for anyone who fetches them', function (): void {
    servePages();

    $this->withHeaders(['User-Agent' => googlebot()])->get('sitemap.xml')->assertOk();
    $this->withHeaders(['User-Agent' => googlebot()])->get('robots.txt')->assertOk();
    $this->withHeaders(['User-Agent' => aBrowser()])->get('sitemap.xml')->assertOk();

    expect(counted())->toBe([
        '- /sitemap.xml served googlebot 1',
        '- /robots.txt served googlebot 1',
        '- /sitemap.xml served direct 1',
    ]);
});

it('counts the sitemap when Google asks whether it changed', function (): void {
    servePages();
    Sitemap::factory()->create(['built_at' => CarbonImmutable::parse('2026-11-06 10:20:00')]);

    $this->withHeaders(['User-Agent' => googlebot(), 'If-Modified-Since' => 'Fri, 06 Nov 2026 10:20:00 GMT'])
        ->get('sitemap.xml')
        ->assertStatus(304);

    expect(counted())->toBe(['- /sitemap.xml served googlebot 1']);
});

it('counts the sitemap of a route group under its prefix', function (): void {
    Route::middleware('web')->prefix('nl')->group(function (): void {
        Route::madeCms(static fn (Page $page): string => $page->title);
    });

    $this->withHeaders(['User-Agent' => googlebot()])->get('nl/sitemap.xml')->assertOk();

    expect(counted())->toBe(['nl /sitemap.xml served googlebot 1']);
});

it('answers as ever when counting fails', function (): void {
    Page::factory()->published()->at('/teams')->create(['title' => 'Teams']);
    servePages();
    Schema::drop('cms_address_hits');

    $this->withHeaders(['User-Agent' => googlebot()])->get('teams')->assertOk()->assertSeeText('Pagina: Teams');
    $this->withHeaders(['User-Agent' => googlebot()])->get('bestaat-niet')->assertNotFound();
    $this->withHeaders(['User-Agent' => googlebot()])->get('sitemap.xml')->assertOk();
});
