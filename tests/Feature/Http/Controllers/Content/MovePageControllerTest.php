<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('moves a page to another place among its siblings and answers nothing', function (): void {
    $home = Page::factory()->atPosition(0)->at('/')->create(['slug' => '']);
    $about = Page::factory()->atPosition(1)->at('/over-ons')->create(['slug' => 'over-ons']);
    $contact = Page::factory()->atPosition(2)->at('/contact')->create(['slug' => 'contact']);

    $this->putJson('made-cms/pages/'.$about->id.'/position', ['parent_id' => null, 'position' => 2])
        ->assertNoContent()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect(Page::query()->roots()->inMenuOrder()->pluck('id')->all())->toBe([$home->id, $contact->id, $about->id]);
});

it('hangs a page under another one and rewrites the addresses of the pages under it', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->atPosition(1)->at('/team')->create(['slug' => 'team']);
    $youth = Page::factory()->under($team)->at('/team/jeugd')->create(['slug' => 'jeugd']);

    $this->putJson('made-cms/pages/'.$team->id.'/position', ['parent_id' => $about->id, 'position' => 0])
        ->assertNoContent();

    expect($team->refresh()->parent_id)->toBe($about->id)
        ->and($team->route?->path)->toBe('/over-ons/team')
        ->and($youth->refresh()->route?->path)->toBe('/over-ons/team/jeugd');
});

it('refuses a move it cannot make, keyed by the field that holds the cause', function (Closure $move, string $field, string $message): void {
    [$page, $input] = $move();

    $this->putJson('made-cms/pages/'.$page->id.'/position', $input)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);
})->with([
    'the home page under another page' => [function (): array {
        $home = Page::factory()->at('/')->create(['slug' => '']);
        $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);

        return [$home, ['parent_id' => $about->id, 'position' => 0]];
    }, 'parent_id', 'De homepagina houdt haar adres en blijft bovenaan.'],
    'under a page below itself' => [function (): array {
        $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
        $team = Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);

        return [$about, ['parent_id' => $team->id, 'position' => 0]];
    }, 'parent_id', 'Onder die pagina kan hij niet liggen: kies een pagina die niet onder deze pagina ligt.'],
    'under a page that is gone' => [function (): array {
        $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);

        return [$about, ['parent_id' => $about->id + 100, 'position' => 0]];
    }, 'parent_id', 'Onder die pagina kan hij niet liggen: kies een pagina die niet onder deze pagina ligt.'],
    'under a deleted page' => [function (): array {
        $gone = Page::factory()->deletedAt(now())->create(['slug' => 'weg']);
        $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);

        return [$about, ['parent_id' => $gone->id, 'position' => 0]];
    }, 'parent_id', 'Onder die pagina kan hij niet liggen: kies een pagina die niet onder deze pagina ligt.'],
    'a place beyond its siblings' => [function (): array {
        $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
        Page::factory()->atPosition(1)->at('/contact')->create(['slug' => 'contact']);

        return [$about, ['parent_id' => null, 'position' => 2]];
    }, 'position', 'Die plek is er niet meer. De boom laat nu zien hoe de pagina’s staan.'],
    'an address another page has' => [function (): array {
        $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);
        Page::factory()->under($about)->at('/over-ons/team')->create(['slug' => 'team']);
        $team = Page::factory()->at('/team')->create(['slug' => 'team']);

        return [$team, ['parent_id' => $about->id, 'position' => 0]];
    }, 'parent_id', 'Daar krijgt hij het adres van een andere pagina. Geef een van beide eerst een ander adres.'],
]);

it('refuses a place that is no place', function (mixed $position): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);

    $this->putJson('made-cms/pages/'.$about->id.'/position', ['parent_id' => null, 'position' => $position])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('position');
})->with(['negative' => -1, 'missing' => null, 'text' => 'boven']);

it('refuses a parent that is no id', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);

    $this->putJson('made-cms/pages/'.$about->id.'/position', ['parent_id' => 'home', 'position' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});

it('refuses an editor without roles and answers 401 to a guest', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    Page::factory()->atPosition(1)->at('/contact')->create(['slug' => 'contact']);

    $this->actingAs(Editor::factory()->withRoles()->create(), 'made-cms')
        ->putJson('made-cms/pages/'.$about->id.'/position', ['parent_id' => null, 'position' => 1])
        ->assertForbidden();

    auth('made-cms')->logout();
    $this->putJson('made-cms/pages/'.$about->id.'/position', ['parent_id' => null, 'position' => 1])->assertUnauthorized();

    expect($about->refresh()->position)->toBe(0);
});
