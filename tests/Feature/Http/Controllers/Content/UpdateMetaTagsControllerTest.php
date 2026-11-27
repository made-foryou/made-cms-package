<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Events\PageMetaUpdated;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->withRoles(Role::SeoSpecialist)->create(), 'made-cms');
});

/**
 * Three points in menu order (A and C without a description, B with one that is too long) and a page in order.
 *
 * @return array{a: Page, b: Page, c: Page, ok: Page}
 */
function metaRound(): array
{
    return [
        'a' => Page::factory()->published()->at('/a')->atPosition(1)->create(['title' => 'A']),
        'b' => Page::factory()->published()->at('/b')->atPosition(2)->withMeta('B', str_repeat('b', 160))->create(['title' => 'B']),
        'c' => Page::factory()->published()->at('/c')->atPosition(3)->create(['title' => 'C']),
        'ok' => Page::factory()->published()->at('/ok')->atPosition(4)->withMeta('In orde', 'Een omschrijving.')->create(['title' => 'In orde']),
    ];
}

it('saves the title and the description, keeps the switches and goes on to the next point', function (): void {
    ['a' => $a, 'b' => $b] = metaRound();
    $a->update(['in_sitemap' => false]);

    $this->put('made-cms/meta-tags/'.$a->id, [
        'meta_title' => 'Over A',
        'meta_description' => 'Alles over A, in een zin.',
        'filter' => 'missing',
        'done' => '2',
    ])->assertRedirect('/made-cms/meta-tags?filter=missing&page='.$b->id.'&done=3#made-cms-afwerken');

    $a->refresh();

    expect($a->meta_title)->toBe('Over A')
        ->and($a->meta_description)->toBe('Alles over A, in een zin.')
        ->and($a->in_sitemap)->toBeFalse()
        ->and($a->indexable)->toBeTrue();

    $this->get('made-cms/meta-tags?filter=missing&page='.$b->id.'&done=3')
        ->assertSee('Titel en omschrijving van A staan live.');
});

it('goes on without counting a point that is still too long', function (): void {
    ['a' => $a, 'b' => $b] = metaRound();

    $this->put('made-cms/meta-tags/'.$a->id, ['meta_title' => '', 'meta_description' => str_repeat('a', 156), 'filter' => 'missing', 'done' => '0'])
        ->assertRedirect('/made-cms/meta-tags?filter=missing&page='.$b->id.'#made-cms-afwerken');
});

it('starts a new round after the last point', function (): void {
    ['c' => $c] = metaRound();

    $this->put('made-cms/meta-tags/'.$c->id, ['meta_title' => '', 'meta_description' => 'Alles over C.', 'filter' => 'long', 'done' => '2'])
        ->assertRedirect('/made-cms/meta-tags?filter=long#made-cms-afwerken');
});

it('goes back to a page that was no point', function (): void {
    ['ok' => $ok] = metaRound();

    $this->put('made-cms/meta-tags/'.$ok->id, ['meta_title' => 'Nog beter', 'meta_description' => 'Een omschrijving.', 'filter' => 'all', 'done' => '1'])
        ->assertRedirect('/made-cms/meta-tags?filter=all&page='.$ok->id.'&done=1#made-cms-afwerken');

    expect($ok->refresh()->meta_title)->toBe('Nog beter');
});

it('tells that the meta of the page changed', function (): void {
    ['a' => $a] = metaRound();
    Event::fake([PageMetaUpdated::class]);

    $this->put('made-cms/meta-tags/'.$a->id, ['meta_title' => '', 'meta_description' => 'Alles over A.']);

    Event::assertDispatched(PageMetaUpdated::class);
});

it('refuses a text far longer than Google shows, back at the page with the message under its field', function (string $field, int $length, string $message): void {
    ['a' => $a] = metaRound();

    $this->put('made-cms/meta-tags/'.$a->id, [$field => str_repeat('x', $length), 'filter' => 'missing', 'done' => '1'])
        ->assertRedirect('/made-cms/meta-tags?filter=missing&page='.$a->id.'&done=1#made-cms-afwerken')
        ->assertSessionHasErrors([$field => $message]);

    expect($a->refresh()->meta_title)->toBeNull();

    $this->get('made-cms/meta-tags?filter=missing&page='.$a->id.'&done=1')
        ->assertSee('<p class="veld-fout" id="made-cms-'.$field.'-fout">'.$message.'</p>', false)
        ->assertSee('aria-invalid="true"', false);
})->with([
    'the title' => ['meta_title', 121, 'Houd de titel in Google op hooguit 120 tekens. Google toont er ongeveer 60.'],
    'the description' => ['meta_description', 321, 'Houd de omschrijving in Google op hooguit 320 tekens. Google toont er ongeveer 155.'],
]);

it('goes on from what the form holds, whatever it holds', function (array $hidden, string $to): void {
    ['a' => $a, 'b' => $b] = metaRound();

    $this->put('made-cms/meta-tags/'.$a->id, ['meta_title' => '', 'meta_description' => 'Alles over A.', ...$hidden])
        ->assertRedirect(str_replace('{b}', (string) $b->id, $to));
})->with([
    'nothing' => [[], '/made-cms/meta-tags?page={b}&done=1#made-cms-afwerken'],
    'an unknown filter' => [['filter' => 'zwembad', 'done' => 'veel'], '/made-cms/meta-tags?page={b}&done=1#made-cms-afwerken'],
    'lists' => [['filter' => ['all'], 'done' => ['3']], '/made-cms/meta-tags?page={b}&done=1#made-cms-afwerken'],
    'a negative round' => [['done' => '-4'], '/made-cms/meta-tags?page={b}&done=1#made-cms-afwerken'],
]);

it('refuses whoever does not open the meta overview', function (): void {
    ['a' => $a] = metaRound();
    $this->actingAs(Editor::factory()->withRoles(Role::ContentManager)->create(), 'made-cms');

    $this->put('made-cms/meta-tags/'.$a->id, ['meta_title' => 'Over A', 'meta_description' => 'Alles over A.'])->assertForbidden();

    expect($a->refresh()->meta_description)->toBeNull();
});

it('sends a guest to the login page', function (): void {
    ['a' => $a] = metaRound();
    auth('made-cms')->logout();

    $this->put('made-cms/meta-tags/'.$a->id, ['meta_title' => 'Over A'])->assertRedirect(route('made-cms-package.login'));
});
