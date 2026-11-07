<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\CheckStripValues;
use Made\CmsPackage\Tests\Fixtures\Strips\Valid\Hero;

/**
 * @return list<array<string, mixed>>
 */
function checkedButtons(): array
{
    return [['tekst' => 'Lid worden', 'gaat_naar' => ['url' => '/lid-worden']]];
}

it('gives every field of the type, a missing one from its default', function (): void {
    $values = CheckStripValues::run(new Hero, ['knoppen' => checkedButtons()]);

    expect(array_keys($values))->toBe(['label', 'kop', 'intro', 'tekst', 'actie', 'knoppen'])
        ->and($values['kop'])->toBe('Welkom')
        ->and($values['label'])->toBeNull();
});

it('gives the values in their stored form', function (): void {
    $values = CheckStripValues::run(new Hero, [
        'kop' => 'TTV Merwestad',
        'tekst' => '<p>Wij <strong>spelen</strong>.</p><script>x</script>',
        'knoppen' => checkedButtons(),
    ]);

    /** @var list<array<string, mixed>> $knoppen */
    $knoppen = $values['knoppen'];

    expect($values['tekst'])->toBe('<p>Wij <strong>spelen</strong>.</p>')
        ->and($knoppen[0]['id'] ?? null)->toBeUuid();
});

it('drops values the type does not know', function (): void {
    $values = CheckStripValues::run(new Hero, ['kop' => 'TTV Merwestad', 'knoppen' => checkedButtons(), 'onbekend' => 'weg']);

    expect($values)->not->toHaveKey('onbekend');
});

it('refuses values that break the definition, keyed by the path of the field', function (): void {
    try {
        CheckStripValues::run(new Hero, ['kop' => null, 'knoppen' => [['tekst' => 'Ga', 'gaat_naar' => ['url' => '/'], 'stijl' => 'groot']]]);
        $this->fail('The values were accepted.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['kop', 'knoppen.0.stijl'])
            ->and($exception->errors()['kop'][0] ?? '')->toBe('Vul Kop in.');
    }
});
