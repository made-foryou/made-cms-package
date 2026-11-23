<?php

declare(strict_types=1);

use Made\CmsPackage\Tests\Fixtures\PageTypes\InlinePageType;
use Made\CmsPackage\Tests\Fixtures\PageTypes\Valid\Nested\LandingPage;
use Made\CmsPackage\Tests\Fixtures\PageTypes\Valid\TeamPage;

it('is named after its class, explains nothing, allows every strip type and starts empty by default', function (): void {
    $landing = new LandingPage;

    expect($landing->label())->toBe('Landing Page')
        ->and($landing->description())->toBeNull()
        ->and($landing->strips())->toBeNull()
        ->and($landing->defaults())->toBe([]);
});

it('allows every strip type when it names none', function (): void {
    expect((new LandingPage)->allows('hero'))->toBeTrue()
        ->and((new LandingPage)->allows('anything'))->toBeTrue();
});

it('allows only the strip types it names', function (): void {
    $type = new InlinePageType('team', ['hero']);

    expect($type->allows('hero'))->toBeTrue()
        ->and($type->allows('cards'))->toBeFalse();
});

it('describes itself for the screens', function (): void {
    expect((new TeamPage)->label())->toBe('Teampagina')
        ->and((new TeamPage)->description())->toBe('Vaste opzet: een hero en de teamkaarten.');
});
