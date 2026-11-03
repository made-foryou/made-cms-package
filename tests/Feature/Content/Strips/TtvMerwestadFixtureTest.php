<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Made\CmsPackage\Content\Strips\StripType;
use Made\CmsPackage\Content\Strips\StripTypes;

/*
| The TTV Merwestad home, coupled by hand (#17), is the acceptance test of
| the scan (4.2, 4.4). These tests keep the fixture in step with the strip
| type API, so it still describes a valid result when the scan is built.
*/

function ttvFixture(string $file): string
{
    return __DIR__.'/../../../Fixtures/Scan/ttv-merwestad/'.$file;
}

/**
 * The strip types the developer wrote for TTV, loaded from the stubs.
 *
 * The stubs keep the namespace of the site (App\Cms\Strips), but they load under a namespace of
 * their own: declaring App\Cms\Strips classes would leak into every test that runs after this one
 * in the same process, such as the discovery tests that expect that namespace to be empty.
 */
function ttvStripTypes(): StripTypes
{
    $namespace = 'Made\\CmsPackage\\Tests\\Fixtures\\Scan\\TtvMerwestad';
    $types = [];

    foreach (glob(ttvFixture('strips/*.php.stub')) ?: [] as $stub) {
        $class = $namespace.'\\'.basename($stub, '.php.stub');

        if (! class_exists($class, false)) {
            $source = (string) file_get_contents($stub);
            expect($source)->toContain('namespace App\\Cms\\Strips;');

            $file = (string) tempnam(sys_get_temp_dir(), 'made-cms-ttv-');
            file_put_contents($file, str_replace('namespace App\\Cms\\Strips;', "namespace {$namespace};", $source));

            try {
                require $file;
            } finally {
                unlink($file);
            }
        }

        if (is_subclass_of($class, StripType::class)) {
            $types[] = new $class;
        }
    }

    return new StripTypes($types);
}

/**
 * @return array{path: string, title: string, strips: list<array{type: string, visible: bool, values: array<string, mixed>}>}
 */
function ttvContent(): array
{
    /** @var array{path: string, title: string, strips: list<array{type: string, visible: bool, values: array<string, mixed>}>} */
    return json_decode((string) file_get_contents(ttvFixture('content.json')), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * The values of every data-cms attribute with the given name, in document order.
 *
 * @return list<string>
 */
function ttvAttributes(string $html, string $name): array
{
    preg_match_all('/\s'.preg_quote($name, '/').'="([^"]*)"/', $html, $matches);

    return $matches[1];
}

it('describes eight valid strip types', function (): void {
    expect(array_map(static fn (StripType $type): string => $type->key(), ttvStripTypes()->all()))->toEqualCanonicalizing([
        'hero', 'cijfers', 'over-ons', 'trainingstijden', 'teams', 'locatie', 'lid-worden', 'contact',
    ]);
});

it('has content that its strip types accept and store unchanged', function (): void {
    $types = ttvStripTypes();

    foreach (ttvContent()['strips'] as $strip) {
        $type = $types->find($strip['type']);
        $validated = Validator::make($strip['values'], $type->rules(), [], $type->attributes())->validate();

        expect([...$type->defaults(), ...$type->normalize($validated)])->toEqual($strip['values']);
    }
});

it('keeps the live home free of cms attributes, as the scan finds it', function (): void {
    $html = (string) file_get_contents(ttvFixture('home.html'));

    expect(substr_count($html, '<section'))->toBe(8)
        ->and($html)->not->toContain('data-cms-');
});

it('marks every strip of the content in the coupled home, in order', function (): void {
    $html = (string) file_get_contents(ttvFixture('home.coupled.html'));

    expect(ttvAttributes($html, 'data-cms-strip'))->toHaveCount(8)
        ->and(ttvAttributes($html, 'data-cms-items'))->toBe(['knoppen', 'cijfers', 'kaarten', 'avonden', 'teams', 'blokken'])
        ->and(ttvAttributes($html, 'data-cms-field'))->toContain('kop', 'kop_accent', 'intro', 'titel', 'naam', 'tijd');

    preg_match('/data-page="([^"]*)"/', $html, $page);
    /** @var array{props: array{strips: list<array{id: string, type: string}>}} $props */
    $props = json_decode(html_entity_decode($page[1]), true, flags: JSON_THROW_ON_ERROR);

    expect(array_column($props['props']['strips'], 'id'))->toBe(ttvAttributes($html, 'data-cms-strip'))
        ->and(array_column($props['props']['strips'], 'type'))->toBe(array_column(ttvContent()['strips'], 'type'));
});
