<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost']);

    $this->home = Page::factory()->published()->atPosition(0)->at('/')->create(['title' => 'Home', 'slug' => '']);
    $this->about = Page::factory()->published()->atPosition(1)->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    $this->team = Page::factory()->published()->atPosition(2)->at('/team')->create(['title' => 'Team', 'slug' => 'team']);
    $this->youth = Page::factory()->published()->under($this->team)->at('/team/jeugd')->create(['title' => 'Jeugd', 'slug' => 'jeugd']);
    $this->contact = Page::factory()->published()->atPosition(3)->at('/contact')->create(['title' => 'Contact', 'slug' => 'contact']);

    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/** The rows of the tree in the browser, as "title@depth". */
function treeRows(): string
{
    return "[...document.querySelectorAll('[data-tree] > li.boomrij')].map((row) => row.querySelector('.boomrij-naam').textContent.trim() + '@' + row.dataset.depth).join(',')";
}

function announced(string $text): string
{
    return waitUntil("document.querySelector('[data-slot=\"tree-announce\"]').textContent === ".json_encode($text, JSON_THROW_ON_ERROR));
}

/**
 * Drags the grip of a page to a half of another row, a number of levels to the right (left when negative), with
 * pointer events at exact places: Playwright's drag lets go on the middle of its target, where above and below meet.
 * Resolves to the label of the line where the page lands just before it is let go, null without a line.
 */
function dragRow(int $from, int $to, string $half, int $levels = 0, bool $escape = false): string
{
    $part = $half === 'below' ? '.75' : '.25';
    $end = $escape
        ? "window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })); window.dispatchEvent(new PointerEvent('pointerup', at(toX, toY)));"
        : "window.dispatchEvent(new PointerEvent('pointerup', at(toX, toY)));";

    return <<<JS
        () => {
            const grip = document.querySelector('[data-page="{$from}"] .greep').getBoundingClientRect();
            const row = document.querySelector('[data-page="{$to}"]').getBoundingClientRect();
            const unit = parseFloat(getComputedStyle(document.querySelector('[data-tree]')).getPropertyValue('--boom-inspring'));
            const x = grip.left + grip.width / 2;
            const y = grip.top + grip.height / 2;
            const toX = x + {$levels} * unit;
            const toY = row.top + row.height * {$part};
            const at = (clientX, clientY) => ({ bubbles: true, cancelable: true, pointerId: 1, isPrimary: true, button: 0, clientX, clientY });

            document.querySelector('[data-page="{$from}"] .greep').dispatchEvent(new PointerEvent('pointerdown', at(x, y)));
            window.dispatchEvent(new PointerEvent('pointermove', at(toX, toY)));
            const label = document.querySelector('[data-tree] .boom-plek')?.textContent.trim() ?? null;
            {$end}

            return label;
        }
        JS;
}

function rootOrder(): array
{
    return Page::query()->roots()->inMenuOrder()->pluck('title')->all();
}

it('moves a page down with its button and keeps the focus on that button', function (): void {
    $tree = visit('/made-cms/pages')->resize(1280, 1800);

    $tree->keys('[data-page="'.$this->about->id.'"] [data-move-to="down"]', 'Enter');

    expect($tree->script(announced('Over ons staat nu op plek 3 van 4.')))->toBeTrue()
        ->and($tree->script('() => '.treeRows()))->toBe('Home@0,Team@0,Jeugd@1,Over ons@0,Contact@0')
        ->and($tree->script('() => document.activeElement.closest("[data-page]")?.dataset.page + ":" + document.activeElement.dataset.moveTo'))->toBe($this->about->id.':down')
        ->and(rootOrder())->toBe(['Home', 'Team', 'Over ons', 'Contact']);

    $tree->assertNoJavaScriptErrors();
});

it('hangs a page under the previous one and one level up again, with the addresses below it', function (): void {
    $tree = visit('/made-cms/pages')->resize(1280, 1800);

    // The buttons show while the pointer is on the row.
    $tree->hover('[data-page="'.$this->team->id.'"] .boomrij-naam')
        ->click('[data-page="'.$this->team->id.'"] [data-move-to="indent"]');

    expect($tree->script(announced('Team staat nu onder Over ons, op plek 1 van 1. Het adres is nu /over-ons/team.')))->toBeTrue()
        ->and($tree->script('() => '.treeRows()))->toBe('Home@0,Over ons@0,Team@1,Jeugd@2,Contact@0')
        ->and($this->team->refresh()->route?->path)->toBe('/over-ons/team')
        ->and($this->youth->refresh()->route?->path)->toBe('/over-ons/team/jeugd');

    $tree->keys('[data-page="'.$this->team->id.'"] [data-move-to="outdent"]', 'Enter');

    expect($tree->script(announced('Team staat nu bovenaan, op plek 3 van 4. Het adres is nu /team.')))->toBeTrue()
        ->and($this->youth->refresh()->route?->path)->toBe('/team/jeugd');

    $tree->assertNoJavaScriptErrors();
});

it('moves a page down by dragging its grip below a row lower in the tree', function (): void {
    $tree = visit('/made-cms/pages')->resize(1280, 1800);

    expect($tree->script(dragRow($this->about->id, $this->contact->id, 'below')))->toBe('Bovenaan')
        ->and($tree->script(announced('Over ons staat nu op plek 4 van 4.')))->toBeTrue()
        ->and($tree->script('() => '.treeRows()))->toBe('Home@0,Team@0,Jeugd@1,Contact@0,Over ons@0')
        ->and(rootOrder())->toBe(['Home', 'Team', 'Contact', 'Over ons']);

    $tree->assertMissing('.boom-plek')
        ->assertMissing('.boomrij.sleept')
        ->assertNoJavaScriptErrors();
});

it('hangs a page with the pages under it below another one by dragging it to the right', function (): void {
    $tree = visit('/made-cms/pages')->resize(1280, 1800);

    expect($tree->script(dragRow($this->team->id, $this->about->id, 'below', levels: 1)))->toBe('Onder Over ons')
        ->and($tree->script(announced('Team staat nu onder Over ons, op plek 1 van 1. Het adres is nu /over-ons/team.')))->toBeTrue()
        ->and($tree->script('() => '.treeRows()))->toBe('Home@0,Over ons@0,Team@1,Jeugd@2,Contact@0')
        ->and($this->team->refresh()->parent_id)->toBe($this->about->id)
        ->and($this->team->route?->path)->toBe('/over-ons/team')
        ->and($this->youth->refresh()->route?->path)->toBe('/over-ons/team/jeugd')
        ->and($tree->script('() => [...document.querySelectorAll("#made-cms-menu .navitem")].map((item) => item.textContent.trim()).join(",")'))
        ->toContain('Over ons,Team,Jeugd');

    $tree->assertNoJavaScriptErrors();
});

it('lets a dragged page go only as deep as three levels and never before the home page', function (): void {
    $board = Page::factory()->published()->under($this->about)->at('/over-ons/bestuur')->create(['title' => 'Bestuur', 'slug' => 'bestuur']);

    $tree = visit('/made-cms/pages')->resize(1280, 1800);

    // Team takes Jeugd along: under Bestuur Jeugd would be the fourth level, so the line stays under Over ons.
    expect($tree->script(dragRow($this->team->id, $board->id, 'below', levels: 2)))->toBe('Onder Over ons')
        ->and($tree->script(announced('Team staat nu onder Over ons, op plek 2 van 2. Het adres is nu /over-ons/team.')))->toBeTrue()
        ->and($tree->script(dragRow($this->contact->id, $this->home->id, 'above')))->toBeNull()
        ->and($tree->script(valueAfter("document.querySelector('[data-slot=\"tree-announce\"]').textContent")))->toBe('Team staat nu onder Over ons, op plek 2 van 2. Het adres is nu /over-ons/team.')
        ->and(rootOrder())->toBe(['Home', 'Over ons', 'Contact']);

    $tree->assertNoJavaScriptErrors();
});

it('drops nothing after Escape', function (): void {
    $tree = visit('/made-cms/pages')->resize(1280, 1800);

    expect($tree->script(dragRow($this->about->id, $this->contact->id, 'below', escape: true)))->toBe('Bovenaan')
        ->and($tree->script(valueAfter("document.querySelector('[data-slot=\"tree-announce\"]').textContent")))->toBe('')
        ->and(rootOrder())->toBe(['Home', 'Over ons', 'Team', 'Contact']);

    $tree->assertMissing('.boom-plek')
        ->assertMissing('.boomrij.sleept')
        ->assertNoJavaScriptErrors();
});

it('says why a button cannot move its page', function (): void {
    Page::factory()->published()->under($this->youth)->at('/team/jeugd/o12')->create(['title' => 'O12', 'slug' => 'o12']);
    $o13 = Page::factory()->published()->atPosition(1)->under($this->youth)->at('/team/jeugd/o13')->create(['title' => 'O13', 'slug' => 'o13']);

    $tree = visit('/made-cms/pages')->resize(1280, 1800);
    $label = fn (Page $page, string $to): mixed => $tree->script('() => document.querySelector(\'[data-page="'.$page->id.'"] [data-move-to="'.$to.'"]\').getAttribute("aria-label")');

    expect($tree->script(waitUntil("document.querySelector('[data-move-to][aria-disabled]') !== null")))->toBeTrue()
        ->and($label($this->about, 'up'))->toBe('Over ons een plek omhoog. De homepagina blijft bovenaan.')
        ->and($label($this->about, 'indent'))->toBe('Over ons onder de vorige pagina hangen. Onder de homepagina hang je geen pagina’s.')
        ->and($label($this->contact, 'down'))->toBe('Contact een plek omlaag. Hij staat al als laatste.')
        ->and($label($this->about, 'outdent'))->toBe('Over ons een niveau omhoog. Hij hangt nergens onder.')
        ->and($label($o13, 'indent'))->toBe('O13 onder de vorige pagina hangen. Dieper dan 3 niveaus gaat via “Ligt onder” op de pagina zelf.')
        ->and($label($this->contact, 'up'))->toBe('Contact een plek omhoog');

    $tree->assertMissing('[data-page="'.$this->home->id.'"] .greep')
        ->assertMissing('[data-page="'.$this->home->id.'"] [data-move-to]');

    // Playwright waits for an aria-disabled button to be enabled, so the editor's click goes through the script.
    $tree->script('() => document.querySelector(\'[data-page="'.$this->contact->id.'"] [data-move-to="down"]\').click()');

    expect($tree->script(valueAfter("document.querySelector('[data-slot=\"tree-announce\"]').textContent")))->toBe('')
        ->and(rootOrder())->toBe(['Home', 'Over ons', 'Team', 'Contact']);

    $tree->assertNoJavaScriptErrors();
});

it('says a refusal above the tree and shows the tree as it is stored', function (): void {
    Page::factory()->published()->under($this->about)->at('/over-ons/team')->create(['title' => 'Ons team', 'slug' => 'team']);

    $tree = visit('/made-cms/pages')->resize(1280, 1800);

    $tree->keys('[data-page="'.$this->team->id.'"] [data-move-to="indent"]', 'Enter')
        ->assertSeeIn('[data-slot="tree-notice"]', 'Daar krijgt hij het adres van een andere pagina. Geef een van beide eerst een ander adres.');

    expect($tree->script('() => '.treeRows()))->toBe('Home@0,Over ons@0,Ons team@1,Team@0,Jeugd@1,Contact@0')
        ->and($this->team->refresh()->parent_id)->toBeNull();

    $tree->click('[data-slot="tree-notice-close"]')
        ->assertMissing('[data-slot="tree-notice"] .melding')
        ->assertNoJavaScriptErrors();
});
