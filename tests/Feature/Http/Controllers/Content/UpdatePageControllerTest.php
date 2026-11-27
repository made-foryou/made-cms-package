<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('saves the basics and says so', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id, ['title' => 'Wie we zijn', 'slug' => 'wie-we-zijn', 'parent_id' => '', 'in_menu' => '0'])
        ->assertRedirect('/made-cms/pages/'.$page->id);

    expect($page->refresh()->title)->toBe('Wie we zijn')
        ->and($page->route?->path)->toBe('/wie-we-zijn')
        ->and($page->in_menu)->toBeFalse();

    $this->get('made-cms/pages/'.$page->id)->assertSee('Wie we zijn is opgeslagen.');
});

it('changes the type of the page', function (): void {
    usePageTypes();
    $page = Page::factory()->at('/teams')->create(['title' => 'Teams', 'slug' => 'teams']);

    $this->put('made-cms/pages/'.$page->id, ['title' => 'Teams', 'slug' => 'teams', 'parent_id' => '', 'in_menu' => '1', 'type' => 'team']);

    expect($page->refresh()->type)->toBe('team');

    $this->put('made-cms/pages/'.$page->id, ['title' => 'Teams', 'slug' => 'teams', 'parent_id' => '', 'in_menu' => '1', 'type' => '']);

    expect($page->refresh()->type)->toBeNull();
});

it('keeps the type when the form does not choose one', function (): void {
    usePageTypes();
    $page = Page::factory()->at('/teams')->create(['title' => 'Teams', 'slug' => 'teams', 'type' => 'team']);

    $this->put('made-cms/pages/'.$page->id, ['title' => 'Onze teams', 'slug' => 'teams', 'parent_id' => '', 'in_menu' => '1']);

    expect($page->refresh()->type)->toBe('team');
});

it('refuses a type the site does not define', function (): void {
    usePageTypes();
    $page = Page::factory()->at('/teams')->create(['title' => 'Teams', 'slug' => 'teams']);

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id, ['title' => 'Teams', 'slug' => 'teams', 'parent_id' => '', 'in_menu' => '1', 'type' => 'contact'])
        ->assertRedirect('/made-cms/pages/'.$page->id)
        ->assertSessionHasErrors(['type' => 'Die soort pagina kent de site niet (meer). Kies een andere soort.']);

    expect($page->refresh()->type)->toBeNull();
});

it('moves the page under another parent', function (): void {
    $about = Page::factory()->atPosition(0)->at('/over-ons')->create(['slug' => 'over-ons']);
    $team = Page::factory()->atPosition(1)->at('/team')->create(['title' => 'Team', 'slug' => 'team']);

    $this->put('made-cms/pages/'.$team->id, ['title' => 'Team', 'slug' => 'team', 'parent_id' => (string) $about->id, 'in_menu' => '1']);

    expect($team->refresh()->route?->path)->toBe('/over-ons/team');
});

it('refuses a parent under the page itself', function (): void {
    $about = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);
    $team = Page::factory()->under($about)->at('/over-ons/team')->create();

    $this->from('/made-cms/pages/'.$about->id)
        ->put('made-cms/pages/'.$about->id, ['title' => 'Over ons', 'slug' => 'over-ons', 'parent_id' => (string) $team->id, 'in_menu' => '1'])
        ->assertRedirect('/made-cms/pages/'.$about->id)
        ->assertSessionHasErrors('parent_id');

    expect($about->refresh()->parent_id)->toBeNull();
});

it('refuses an address another page has', function (): void {
    Page::factory()->at('/contact')->create();
    $page = Page::factory()->at('/over-ons')->create(['slug' => 'over-ons']);

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id, ['title' => 'Over ons', 'slug' => 'contact', 'parent_id' => '', 'in_menu' => '1'])
        ->assertSessionHasErrors(['slug' => 'Dat adres heeft al een andere pagina.']);
});

it('refuses an address that gets longer than 120 characters once it is made', function (): void {
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id, ['title' => 'Over ons', 'slug' => str_repeat('щ', 50), 'parent_id' => '', 'in_menu' => '1'])
        ->assertSessionHasErrors(['slug' => 'Houd het adres op hooguit 120 tekens. Een letter als ß of щ telt daarin als meer tekens.']);

    expect($page->refresh()->slug)->toBe('over-ons')
        ->and($page->route?->path)->toBe('/over-ons');
});

it('refuses an address that makes the address of a page under it longer than 255 characters', function (): void {
    $page = Page::factory()->at('/'.str_repeat('p', 10))->create(['title' => 'Pagina', 'slug' => str_repeat('p', 10)]);
    $child = Page::factory()->under($page)->at('/'.str_repeat('p', 10).'/'.str_repeat('c', 120))->create(['slug' => str_repeat('c', 120)]);
    Page::factory()->under($child)->at('/'.str_repeat('p', 10).'/'.str_repeat('c', 120).'/'.str_repeat('g', 120))->create(['slug' => str_repeat('g', 120)]);

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id, ['title' => 'Pagina', 'slug' => str_repeat('p', 13), 'parent_id' => '', 'in_menu' => '1'])
        ->assertSessionHasErrors(['slug' => 'Het hele adres wordt dan langer dan 255 tekens, bij deze pagina of een pagina eronder. Kies een korter adres.']);

    expect($page->refresh()->route?->path)->toBe('/'.str_repeat('p', 10));
});

it('keeps the address of the home page when the form has no address', function (): void {
    $home = Page::factory()->at('/')->create(['title' => 'Home', 'slug' => 'home']);

    $this->put('made-cms/pages/'.$home->id, ['title' => 'Welkom', 'in_menu' => '1'])
        ->assertRedirect('/made-cms/pages/'.$home->id);

    expect($home->refresh()->title)->toBe('Welkom')
        ->and($home->route?->path)->toBe('/');
});

it('hides the page with the switch for visitors and shows it again', function (): void {
    $page = Page::factory()->published()->at('/privacy')->create(['title' => 'Privacy', 'slug' => 'privacy']);
    $basics = ['title' => 'Privacy', 'slug' => 'privacy', 'parent_id' => '', 'in_menu' => '1'];

    $this->put('made-cms/pages/'.$page->id, [...$basics, 'visible' => '0'])->assertRedirect('/made-cms/pages/'.$page->id);

    expect($page->refresh()->hidden)->toBeTrue();

    $this->put('made-cms/pages/'.$page->id, [...$basics, 'visible' => '1']);

    expect($page->refresh()->hidden)->toBeFalse();
});

it('keeps whether visitors see the page when the form has no switch for it', function (): void {
    $page = Page::factory()->published()->hidden()->at('/privacy')->create(['title' => 'Privacy', 'slug' => 'privacy']);

    $this->put('made-cms/pages/'.$page->id, ['title' => 'Privacyverklaring', 'slug' => 'privacy', 'parent_id' => '', 'in_menu' => '1']);

    expect($page->refresh()->title)->toBe('Privacyverklaring')
        ->and($page->hidden)->toBeTrue();
});

it('notes the editor who saves the basics', function (): void {
    $editor = Editor::factory()->create();
    $this->actingAs($editor, 'made-cms');
    $page = Page::factory()->at('/over-ons')->create(['title' => 'Over ons', 'slug' => 'over-ons']);

    $this->put('made-cms/pages/'.$page->id, ['title' => 'Wie we zijn', 'slug' => 'over-ons', 'parent_id' => '', 'in_menu' => '1']);

    expect($page->refresh()->updated_by)->toBe($editor->id);
});

describe('Publiceren op een moment', function (): void {
    beforeEach(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-27 14:00:00'));
        $this->page = Page::factory()->withDraft([])->at('/teams')->create(['title' => 'Teams', 'slug' => 'teams']);
        $this->basics = ['title' => 'Teams', 'slug' => 'teams', 'parent_id' => '', 'in_menu' => '1'];
    });

    it('plans the publication with the basics and says it is saved', function (): void {
        $editor = Editor::factory()->create();
        $this->actingAs($editor, 'made-cms');

        $this->put('made-cms/pages/'.$this->page->id, [...$this->basics, 'scheduled' => '1', 'publish_at' => '2026-11-28T09:00'])
            ->assertRedirect('/made-cms/pages/'.$this->page->id);

        expect($this->page->refresh()->publish_at?->toDateTimeString())->toBe('2026-11-28 09:00:00')
            ->and($this->page->scheduled_by)->toBe($editor->id);

        $this->get('made-cms/pages/'.$this->page->id)->assertSee('Teams is opgeslagen.');
    });

    it('takes a moment with seconds as the browser may send it', function (): void {
        $this->put('made-cms/pages/'.$this->page->id, [...$this->basics, 'scheduled' => '1', 'publish_at' => '2026-11-28T09:00:00']);

        expect($this->page->refresh()->publish_at?->toDateTimeString())->toBe('2026-11-28 09:00:00');
    });

    it('reads the moment in the timezone of the app', function (): void {
        config(['app.timezone' => 'Europe/Amsterdam']);

        $this->put('made-cms/pages/'.$this->page->id, [...$this->basics, 'scheduled' => '1', 'publish_at' => '2026-11-28T10:00']);

        expect(DB::table('cms_pages')->where('id', $this->page->id)->value('publish_at'))->toStartWith('2026-11-28 09:00:00');
    });

    it('takes the planned publication away with the switch off', function (): void {
        $this->page->forceFill(['publish_at' => '2026-11-28 09:00:00'])->save();

        $this->put('made-cms/pages/'.$this->page->id, [...$this->basics, 'scheduled' => '0', 'publish_at' => '2026-11-28T09:00']);

        expect($this->page->refresh()->publish_at)->toBeNull();
    });

    it('keeps the planned publication when the form has no switch for it', function (): void {
        $this->page->forceFill(['publish_at' => '2026-11-28 09:00:00'])->save();

        $this->put('made-cms/pages/'.$this->page->id, [...$this->basics, 'title' => 'Onze teams']);

        expect($this->page->refresh()->publish_at?->toDateTimeString())->toBe('2026-11-28 09:00:00');
    });

    it('keeps a planned moment that passed when the basics are saved', function (): void {
        $this->page->forceFill(['publish_at' => '2026-11-27 09:00:00'])->save();

        $this->put('made-cms/pages/'.$this->page->id, [...$this->basics, 'title' => 'Onze teams', 'scheduled' => '1', 'publish_at' => '2026-11-27T09:00'])
            ->assertSessionHasNoErrors();

        expect($this->page->refresh()->title)->toBe('Onze teams')
            ->and($this->page->publish_at?->toDateTimeString())->toBe('2026-11-27 09:00:00');
    });

    it('refuses another moment that passed and saves nothing', function (): void {
        $this->from('/made-cms/pages/'.$this->page->id)
            ->put('made-cms/pages/'.$this->page->id, [...$this->basics, 'title' => 'Onze teams', 'scheduled' => '1', 'publish_at' => '2026-11-27T13:00'])
            ->assertRedirect('/made-cms/pages/'.$this->page->id)
            ->assertSessionHasErrors(['publish_at' => 'Dat moment is al voorbij. Kies een moment in de toekomst.']);

        expect($this->page->refresh()->title)->toBe('Teams')
            ->and($this->page->publish_at)->toBeNull();
    });

    it('asks for the moment when the switch is on', function (string $moment): void {
        $this->from('/made-cms/pages/'.$this->page->id)
            ->put('made-cms/pages/'.$this->page->id, [...$this->basics, 'scheduled' => '1', 'publish_at' => $moment])
            ->assertSessionHasErrors(['publish_at' => 'Kies de datum en de tijd waarop hij live gaat.']);

        expect($this->page->refresh()->publish_at)->toBeNull();
    })->with(['', 'morgen', '28-11-2026 09:00']);

    it('shows the refusal at the field with the switch on', function (): void {
        $this->from('/made-cms/pages/'.$this->page->id)
            ->put('made-cms/pages/'.$this->page->id, [...$this->basics, 'scheduled' => '1', 'publish_at' => '2026-11-27T13:00']);

        expect((string) $this->get('made-cms/pages/'.$this->page->id)->getContent())
            ->toContain('id="made-cms-scheduled" name="scheduled" value="1" checked aria-describedby')
            ->toContain('value="2026-11-27T13:00"')
            ->toContain('<p class="veld-fout" id="made-cms-publish_at-fout">Dat moment is al voorbij. Kies een moment in de toekomst.</p>');
    });
});
