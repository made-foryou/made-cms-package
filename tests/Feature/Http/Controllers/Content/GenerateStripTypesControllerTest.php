<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Data\GeneratedStripType;
use Made\CmsPackage\Content\Data\GeneratedStripTypes;
use Made\CmsPackage\Content\Data\SkippedStripType;
use Made\CmsPackage\Content\Enums\StripTypeSkip;
use Made\CmsPackage\Http\Responses\GeneratedStripTypesResponse;

beforeEach(function (): void {
    $this->app->detectEnvironment(static fn (): string => 'local');

    ['folder' => $this->folder, 'namespace' => $this->namespace] = useStripFolder();

    $this->actingAs(Editor::factory()->withRoles(Role::Admin)->create(), 'made-cms');
});

/**
 * Posts a scan result as the scan panel will, with the token of the session: outside the tests, CSRF counts.
 *
 * @param  array<mixed>  $scan
 */
function postScan(array $scan): TestResponse
{
    return test()->withSession(['_token' => 'scan-token'])
        ->withHeader('X-CSRF-TOKEN', 'scan-token')
        ->postJson('made-cms/scan/strip-types', $scan);
}

/**
 * A scan result with one strip type per key, each with a heading.
 *
 * @return array<string, mixed>
 */
function scanOf(string ...$keys): array
{
    return [
        'version' => 1,
        'types' => array_map(static fn (string $key): array => [
            'key' => $key,
            'label' => ucfirst($key),
            'fields' => [['key' => 'kop', 'type' => 'text', 'selector' => '', 'required' => true]],
        ], array_values($keys)),
        'pages' => [],
    ];
}

it('writes the strip types of a scan for an admin and says what it wrote', function (): void {
    $response = postScan(ttvScan()->toArray())
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('skipped', [])
        ->assertJsonPath('summary', '8 striptypes geschreven')
        ->assertJsonPath('written.0', [
            'key' => 'strook-1',
            'class' => $this->namespace.'\\Strook1',
            'path' => $this->folder.'/Strook1.php',
            'left_out' => ['afbeelding'],
        ]);

    expect(array_column((array) $response->json('written'), 'key'))->toBe([
        'strook-1', 'strook-2', 'over-ons', 'trainingstijden', 'teams', 'locatie', 'lid-worden', 'contact',
    ])
        ->and(is_file($this->folder.'/Trainingstijden.php'))->toBeTrue();
});

it('says which strip types it skipped and why', function (): void {
    useStripTypes();

    postScan(scanOf('hero', 'quote', 'Over ons'))
        ->assertOk()
        ->assertJsonPath('summary', '1 striptype geschreven, 2 overgeslagen')
        ->assertJsonPath('written.0.key', 'quote')
        ->assertJsonPath('skipped', [
            ['key' => 'hero', 'class' => $this->namespace.'\\Hero', 'reason' => 'key_exists', 'label' => 'Er is al een striptype met deze key.', 'detail' => null],
            [
                'key' => 'Over ons',
                'class' => $this->namespace.'\\OverOns',
                'reason' => 'invalid',
                'label' => 'De definitie klopt niet.',
                'detail' => "Strip type [{$this->namespace}\\OverOns] has an invalid key [Over ons]; use lowercase letters, digits and dashes.",
            ],
        ]);
});

it('refuses the scan outside the local environment, also to an admin, and writes nothing', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');

    try {
        postScan(scanOf('quote'))->assertForbidden();
    } finally {
        // Tearing down in production would ask to confirm the migrations.
        $this->app->detectEnvironment(static fn (): string => 'testing');
    }

    expect(file_exists($this->folder))->toBeFalse();
});

it('refuses the scan to an editor without the admin role', function (): void {
    $this->actingAs(Editor::factory()->withRoles(Role::Webmaster)->create(), 'made-cms');

    postScan(scanOf('quote'))->assertForbidden();

    expect(file_exists($this->folder))->toBeFalse();
});

it('refuses a guest', function (): void {
    auth('made-cms')->logout();

    postScan(scanOf('quote'))->assertUnauthorized();
});

it('refuses what is no scan result it can read', function (array $scan, string $field, string $message): void {
    postScan($scan)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field => $message]);

    expect(file_exists($this->folder))->toBeFalse();
})->with([
    'nothing' => [[], 'types', 'Het scanresultaat heeft geen striptypes met velden in de goede vorm.'],
    'another version' => [[...scanOf('quote'), 'version' => 2], 'version', 'Dit scanresultaat komt uit een andere versie van de scan.'],
    'no types' => [[...scanOf(), 'types' => []], 'types', 'Het scanresultaat heeft geen striptypes met velden in de goede vorm.'],
    'a key twice' => [scanOf('quote', 'quote'), 'types.1.key', 'Het scanresultaat heeft geen striptypes met velden in de goede vorm.'],
    'an unknown field type' => [
        ['version' => 1, 'types' => [['key' => 'quote', 'label' => 'Quote', 'fields' => [['key' => 'kaart', 'type' => 'map']]]]],
        'types.0.fields.0.type',
        'Het scanresultaat heeft geen striptypes met velden in de goede vorm.',
    ],
    'a max that is no number' => [
        ['version' => 1, 'types' => [['key' => 'quote', 'label' => 'Quote', 'fields' => [['key' => 'items', 'type' => 'items', 'max' => 'drie', 'fields' => [['key' => 'regel', 'type' => 'text']]]]]]],
        'types.0.fields.0.max',
        'Het scanresultaat heeft geen striptypes met velden in de goede vorm.',
    ],
    'a field of a list without a type' => [
        ['version' => 1, 'types' => [['key' => 'quote', 'label' => 'Quote', 'fields' => [['key' => 'items', 'type' => 'items', 'fields' => [['key' => 'regel']]]]]]],
        'types.0.fields.0.fields.0.type',
        'Het scanresultaat heeft geen striptypes met velden in de goede vorm.',
    ],
]);

it('names a written file from the root of the site when it lies there', function (): void {
    $response = GeneratedStripTypesResponse::of(new GeneratedStripTypes(
        [new GeneratedStripType('hero', 'App\\Cms\\Strips\\Hero', base_path('app/Cms/Strips/Hero.php'))],
        [new SkippedStripType('cards', 'App\\Cms\\Strips\\Cards', StripTypeSkip::ClassExists)],
    ));

    expect($response->getData(true))->toBe([
        'written' => [['key' => 'hero', 'class' => 'App\\Cms\\Strips\\Hero', 'path' => 'app/Cms/Strips/Hero.php', 'left_out' => []]],
        'skipped' => [['key' => 'cards', 'class' => 'App\\Cms\\Strips\\Cards', 'reason' => 'class_exists', 'label' => 'Er is al een klasse met deze naam.', 'detail' => null]],
        'summary' => '1 striptype geschreven, 1 overgeslagen',
    ]);
});
