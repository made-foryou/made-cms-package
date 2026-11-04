<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    useStripTypes();
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

/**
 * Valid values for every field of the fixture Hero.
 *
 * @return array<string, mixed>
 */
function panelHero(): array
{
    return [
        'label' => 'Tafeltennis',
        'kop' => 'TTV Merwestad',
        'intro' => 'Sinds 1982.',
        'tekst' => '<p>Welkom</p>',
        'actie' => ['text' => 'Lid worden', 'link' => ['url' => '/lid-worden']],
        'knoppen' => [['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden'], 'stijl' => 'primary']],
    ];
}

function heroPage(): Page
{
    return Page::factory()->published()->withStrips([storedStrip('hero', panelHero(), id: 'a')])->create();
}

it('saves the values of a strip in the draft and answers with what it stored', function (): void {
    $page = heroPage();

    $response = $this->putJson('made-cms/pages/'.$page->id.'/strips/a', ['values' => [...panelHero(), 'kop' => 'Nieuwe kop']])
        ->assertOk()
        ->assertJsonPath('strip.id', 'a')
        ->assertJsonPath('strip.visible', true)
        ->assertJsonPath('strip.values.kop', 'Nieuwe kop');

    $stored = $page->refresh();

    expect($stored->draft_strips[0]['values']['kop'] ?? null)->toBe('Nieuwe kop')
        ->and($stored->strips[0]['values']['kop'] ?? null)->toBe('TTV Merwestad')
        ->and($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('gives a new item an id and hands it back', function (): void {
    $page = heroPage();
    $knop = ['tekst' => 'Meer', 'gaat_naar' => ['url' => '#over-ons']];

    $values = $this->putJson('made-cms/pages/'.$page->id.'/strips/a', ['values' => [...panelHero(), 'knoppen' => [$knop]]])
        ->assertOk()
        ->json('strip.values');

    expect($values['knoppen'][0]['id'])->toBeString()->not->toBeEmpty()
        ->and($page->refresh()->draft_strips[0]['values']['knoppen'][0]['id'] ?? null)->toBe($values['knoppen'][0]['id']);
});

it('cleans rich text before it stores it', function (): void {
    $page = heroPage();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a', ['values' => [...panelHero(), 'tekst' => '<p><b>Vet</b> en <strong>sterk</strong></p><script>x</script>']])
        ->assertOk()
        ->assertJsonPath('strip.values.tekst', '<p>Vet en <strong>sterk</strong></p>');
});

it('stores a link to a page of the site', function (): void {
    $about = Page::factory()->create();
    $page = heroPage();
    $knop = ['tekst' => 'Over ons', 'gaat_naar' => ['page' => (string) $about->id]];

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a', ['values' => [...panelHero(), 'knoppen' => [$knop]]])
        ->assertOk()
        ->assertJsonPath('strip.values.knoppen.0.gaat_naar', ['page' => $about->id]);
});

it('refuses values that break the strip type, with a message per field', function (array $change, string $path, string $message): void {
    $page = heroPage();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a', ['values' => [...panelHero(), ...$change]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$path => $message]);

    expect($page->refresh()->draft_strips)->toBeNull();
})->with([
    'text: required' => [['kop' => ''], 'kop', 'Vul Kop in.'],
    'text: too long' => [['label' => str_repeat('a', 256)], 'label', 'Label mag niet langer zijn dan 255 tekens.'],
    'long text: not text' => [['intro' => ['a']], 'intro', 'Intro moet tekst zijn.'],
    'rich text: too long' => [['tekst' => str_repeat('a', 65536)], 'tekst', 'Tekst mag niet langer zijn dan 65535 tekens.'],
    'link: unknown page' => [['knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['page' => 999]]]], 'knoppen.0.gaat_naar', 'De gekozen pagina bij Gaat naar bestaat niet.'],
    'link: invalid address' => [['knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['url' => 'javascript:alert(1)']]]], 'knoppen.0.gaat_naar', 'Het adres bij Gaat naar is ongeldig. Begin met https://, mailto:, tel:, / of #.'],
    'button: no text' => [['actie' => ['link' => ['url' => '/']]], 'actie.text', 'Vul Tekst in.'],
    'choice: outside the options' => [['knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['url' => '/'], 'stijl' => 'groot']]], 'knoppen.0.stijl', 'Kies bij Stijl een van de mogelijkheden.'],
    'items: too few' => [['knoppen' => []], 'knoppen', 'Knoppen heeft er minstens 1 nodig.'],
    'items: too many' => [['knoppen' => array_fill(0, 3, ['tekst' => 'Ga', 'gaat_naar' => ['url' => '/']])], 'knoppen', 'Knoppen mag er niet meer dan 2 hebben.'],
    'items: unknown field' => [['knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['url' => '/'], 'kleur' => 'rood']]], 'knoppen.0', 'Item heeft niet de juiste vorm.'],
]);

it('wants the values of the strip', function (): void {
    $page = heroPage();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('values');
});

it('answers in English when the launcher locale is English', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);
    $page = heroPage();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a', ['values' => [...panelHero(), 'kop' => '']])
        ->assertJsonValidationErrors(['kop' => 'Fill in Kop.']);
});

it('answers 404 for a strip that is not on the page or of an unknown type', function (string $id): void {
    $page = Page::factory()->withDraft([storedStrip('hero', panelHero(), id: 'a'), storedStrip('oud', ['x' => 1], id: 'b')])->create();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/'.$id, ['values' => panelHero()])->assertNotFound();
})->with(['not on the page' => 'x', 'unknown type' => 'b']);

it('answers 401 to a guest', function (): void {
    $page = heroPage();
    auth('made-cms')->logout();

    $this->putJson('made-cms/pages/'.$page->id.'/strips/a', ['values' => panelHero()])->assertUnauthorized();
});
