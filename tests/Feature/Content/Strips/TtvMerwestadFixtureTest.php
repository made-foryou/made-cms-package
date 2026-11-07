<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Made\CmsPackage\Content\Strips\StripType;

/*
| The TTV Merwestad home, coupled by hand (#17), is the acceptance test of
| the scan (4.2, 4.4). These tests keep the fixture in step with the strip
| type API, so it still describes a valid result when the scan is built.
*/

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

it('has content that its strip types accept and store unchanged, apart from the ids its items get', function (): void {
    $types = ttvStripTypes();

    foreach (ttvContent()['strips'] as $strip) {
        $type = $types->find($strip['type']);
        $validated = Validator::make($strip['values'], $type->rules(), [], $type->attributes())->validate();
        $stored = [...$type->defaults(), ...$type->normalize($validated)];
        $withoutIds = [];

        foreach ($stored as $key => $value) {
            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $item) {
                    expect($item)->toBeArray()->toHaveKey('id')->and($item['id'] ?? null)->toBeUuid();
                }

                $value = array_map(static fn (mixed $item): mixed => is_array($item) ? array_diff_key($item, ['id' => true]) : $item, $value);
            }

            $withoutIds[$key] = $value;
        }

        expect($withoutIds)->toEqual($strip['values']);
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
