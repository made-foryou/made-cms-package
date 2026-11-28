<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Enums\VisitSource;

it('tells a crawler from a person', function (VisitSource $source, bool $crawler): void {
    expect($source->isCrawler())->toBe($crawler);
})->with([
    'google' => [VisitSource::Google, false],
    'another search engine' => [VisitSource::SearchEngine, false],
    'a link on the site' => [VisitSource::Internal, false],
    'a link elsewhere' => [VisitSource::External, false],
    'direct' => [VisitSource::Direct, false],
    'googlebot' => [VisitSource::Googlebot, true],
    'another bot' => [VisitSource::Bot, true],
]);

it('splits the sources into people and crawlers', function (): void {
    expect(VisitSource::crawlers())->toBe([VisitSource::Googlebot, VisitSource::Bot])
        ->and(VisitSource::people())->toBe([
            VisitSource::Google,
            VisitSource::SearchEngine,
            VisitSource::Internal,
            VisitSource::External,
            VisitSource::Direct,
        ]);
});
