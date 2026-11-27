<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Content\Actions\ListAttentionByPage;
use Made\CmsPackage\Content\Actions\SyncMediaUsage;
use Made\CmsPackage\Content\Data\AttentionItem;
use Made\CmsPackage\Content\Enums\AttentionReason;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\StripTypes;
use Made\CmsPackage\Tests\Fixtures\Strips\InlineStripType;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;

beforeEach(function (): void {
    config(['app.url' => 'https://example.test', 'app.name' => 'TTV Merwestad']);
});

it('gives what every page asks for attention by its id', function (): void {
    $teams = Page::factory()->published()->withMeta('Onze teams')->at('/teams')->create();
    $about = Page::factory()->published()->withMeta(null, 'Wie we zijn.')->at('/over-ons')->create(['title' => 'Over ons']);

    $attention = ListAttentionByPage::run();

    expect(array_map(static fn (AttentionItem $item): AttentionReason => $item->reason, $attention->of($teams->id)->items))
        ->toBe([AttentionReason::MetaDescriptionMissing])
        ->and($attention->of($about->id)->isEmpty())->toBeTrue();
});

it('leaves out that a page is still empty, which only its own screen points out', function (): void {
    $empty = Page::factory()->at('/jeugd')->create();

    expect(ListAttentionByPage::run()->of($empty->id)->isEmpty())->toBeTrue();
});

it('leaves out the deleted pages', function (): void {
    $page = Page::factory()->published()->at('/teams')->deletedAt(now())->create();

    expect(ListAttentionByPage::run()->of($page->id)->isEmpty())->toBeTrue();
});

it('tells which pages give a visitor more than one h1 in their strips', function (): void {
    app()->instance(StripTypes::class, new StripTypes([new InlineStripType('kop', [Heading::make('kop')->tag('h1')], 'Kop')]));
    $teams = Page::factory()->published()->withMeta('Onze teams', 'Alle teams.')->at('/teams')
        ->withStrips([storedStrip('kop', ['kop' => 'Onze teams']), storedStrip('kop', ['kop' => 'Jeugd'])])
        ->create();
    $about = Page::factory()->published()->withMeta('Over ons', 'Wie we zijn.')->at('/over-ons')
        ->withStrips([storedStrip('kop', ['kop' => 'Over ons'])])
        ->create();

    $attention = ListAttentionByPage::run();

    expect(array_map(static fn (AttentionItem $item): AttentionReason => $item->reason, $attention->of($teams->id)->items))
        ->toBe([AttentionReason::ManyH1])
        ->and($attention->of($about->id)->isEmpty())->toBeTrue();
});

it('flags every page that shares its title in Google with another, but not a noindex twin', function (): void {
    config(['app.name' => 'TTV Merwestad']);
    $contact = Page::factory()->published()->withMeta('Contact | TTV Merwestad', 'Bel of mail ons.')->at('/contact')->create(['title' => 'Contact']);
    $join = Page::factory()->published()->withMeta('contact | ttv merwestad', 'Word lid.')->at('/lid-worden')->create(['title' => 'Lid worden']);
    $intern = Page::factory()->published()->noindex()->withMeta('Contact | TTV Merwestad', 'Intern.')->at('/intern')->create(['title' => 'Intern']);

    $attention = ListAttentionByPage::run();
    $reasons = static fn (Page $page): array => array_map(static fn (AttentionItem $item): AttentionReason => $item->reason, $attention->of($page->id)->items);

    expect($reasons($contact))->toBe([AttentionReason::MetaTitleShared])
        ->and($attention->of($contact->id)->items[0]->names)->toBe(['Lid worden'])
        ->and($reasons($join))->toBe([AttentionReason::MetaTitleShared])
        ->and($reasons($intern))->toBe([]);
});

it('reads the pages, their addresses and their photos without an alt text once, however many pages there are', function (int $count): void {
    app()->instance(StripTypes::class, new StripTypes([new Hero, new InlineStripType('kop', [Heading::make('kop')->tag('h1')], 'Kop')]));

    // Published, shown in Google and without a title of their own: the title Google gets needs the address. Their
    // strips give two h1's, which the source reads from the strips loaded with the page.
    for ($i = 0; $i < $count; $i++) {
        $page = Page::factory()->published()->at('/pagina-'.$i)
            ->withStrips([storedStrip('hero', ['foto' => Media::factory()->create()->id]), storedStrip('kop', ['kop' => 'Een']), storedStrip('kop', ['kop' => 'Twee'])])
            ->create();
        SyncMediaUsage::run($page);
    }

    DB::enableQueryLog();
    $attention = ListAttentionByPage::run();

    expect(DB::getQueryLog())->toHaveCount(3)
        ->and($attention->flagged())->toBe($count)
        ->and($attention->reasons())->toContain(AttentionReason::ImageWithoutAlt, AttentionReason::ManyH1);
})->with([3, 30]);
