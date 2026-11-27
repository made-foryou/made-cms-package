<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\SuggestPageDescription;
use Made\CmsPackage\Content\Data\DescriptionSources;
use Made\CmsPackage\Content\Data\DescriptionSuggestion;
use Made\CmsPackage\Content\Enums\SuggestionOrigin;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Suggestions\DescriptionSource;

/**
 * A source that proposes the same text for every page, or nothing.
 */
function fixedDescriptionSource(?string $text): DescriptionSource
{
    return new readonly class($text) implements DescriptionSource
    {
        public function __construct(private ?string $text) {}

        public function suggest(Page $page): ?DescriptionSuggestion
        {
            return $this->text === null ? null : new DescriptionSuggestion($this->text, SuggestionOrigin::PageText);
        }
    };
}

it('proposes a description from the text of the page', function (): void {
    useStripTypes();
    $page = Page::factory()->published()->withStrips([storedStrip('hero', ['intro' => 'We trainen op dinsdag en donderdag in Mountain Network.'])])->create();

    expect(SuggestPageDescription::run($page))->toEqual(new DescriptionSuggestion(
        'We trainen op dinsdag en donderdag in Mountain Network.',
        SuggestionOrigin::PageText,
    ));
});

it('takes the first proposal that fits on one line, passing by one that is empty or too long', function (): void {
    app()->instance(DescriptionSources::class, new DescriptionSources([
        fixedDescriptionSource(null),
        fixedDescriptionSource(str_repeat('a', 156)),
        fixedDescriptionSource("  \n "),
        fixedDescriptionSource("  Een  goede\nomschrijving. "),
        fixedDescriptionSource('Een latere omschrijving.'),
    ]));

    expect(SuggestPageDescription::run(Page::factory()->create()))
        ->toEqual(new DescriptionSuggestion('Een goede omschrijving.', SuggestionOrigin::PageText));
});

it('proposes nothing when no source has anything', function (): void {
    app()->instance(DescriptionSources::class, new DescriptionSources([fixedDescriptionSource(null)]));

    expect(SuggestPageDescription::run(Page::factory()->create()))->toBeNull();
});
