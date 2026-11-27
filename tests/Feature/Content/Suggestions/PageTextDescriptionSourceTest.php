<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\DescriptionSuggestion;
use Made\CmsPackage\Content\Enums\SuggestionOrigin;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Suggestions\PageTextDescriptionSource;

beforeEach(function (): void {
    useStripTypes();
});

function suggestionFrom(Page $page): ?DescriptionSuggestion
{
    return (new PageTextDescriptionSource)->suggest($page);
}

it('proposes the first whole sentences of the first running text a visitor sees', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['kop' => 'Trainingstijden', 'intro' => "We trainen op dinsdag en donderdag in Mountain Network.\nKom gerust een keer vrijblijvend meespelen."]),
        storedStrip('hero', ['intro' => 'Een tweede strook met een andere tekst erin.']),
    ])->create();

    expect(suggestionFrom($page))->toEqual(new DescriptionSuggestion(
        'We trainen op dinsdag en donderdag in Mountain Network. Kom gerust een keer vrijblijvend meespelen.',
        SuggestionOrigin::PageText,
    ));
});

it('takes the next running text when one gives no sentence of some length', function (): void {
    $page = Page::factory()->published()->withStrips([
        storedStrip('hero', ['intro' => 'Kom langs.', 'tekst' => '<p>Welkom</p><p>Wij spelen <strong>tafeltennis</strong> in Dordrecht, voor jong en oud.</p>']),
    ])->create();

    expect(suggestionFrom($page)?->text)->toBe('Wij spelen tafeltennis in Dordrecht, voor jong en oud.');
});

it('reads the published strips, never the draft', function (): void {
    $page = Page::factory()->published()
        ->withStrips([storedStrip('hero', ['intro' => 'De tekst die bezoekers nu op de pagina lezen.'])])
        ->withDraft([storedStrip('hero', ['intro' => 'Een nieuwe tekst die nog in het concept staat.'])])
        ->create();

    expect(suggestionFrom($page)?->text)->toBe('De tekst die bezoekers nu op de pagina lezen.');
});

it('proposes nothing from what a visitor does not see or without sensible text', function (array $strips): void {
    expect(suggestionFrom(Page::factory()->published()->withStrips($strips)->create()))->toBeNull();
})->with([
    'no strips' => [[]],
    'a hidden strip' => [[storedStrip('hero', ['intro' => 'Een tekst die niemand op de pagina ziet staan.'], visible: false)]],
    'a type the site no longer has' => [[storedStrip('weg', ['intro' => 'Een tekst van een strook die niet meer bestaat.'])]],
    'too short' => [[storedStrip('hero', ['intro' => 'Kom gerust langs.'])]],
    'no sentence' => [[storedStrip('hero', ['intro' => 'Welkom bij TTV Merwestad'])]],
    'only a short text' => [[storedStrip('hero', ['kop' => 'Welkom bij de leukste tafeltennisclub van Dordrecht.'])]],
]);
