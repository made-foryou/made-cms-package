<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\GenerateStripTypes;
use Made\CmsPackage\Content\Data\GeneratedStripType;
use Made\CmsPackage\Content\Strips\Fields\Field;
use Made\CmsPackage\Content\Strips\Fields\Heading;
use Made\CmsPackage\Content\Strips\Fields\Items;
use Made\CmsPackage\Content\Strips\StripType;

/*
| The acceptance test of 4.4 (#31): the classes the scan writes for the live
| TTV Merwestad home hold, on the main lines, what the developer wrote by hand
| when coupling it (#17): the same strip types, fields and field types. The
| names differ without the AI step. Every other difference is a limit of the
| scan that docs/scanresultaat.md names.
*/

beforeEach(function (): void {
    $this->app->detectEnvironment(static fn (): string => 'local');

    useStripFolder();
});

/**
 * Per handwritten strip type, in the order of the page: the scanned type and its fields that are a field of the
 * developer under another name (scanned => handwritten, a field of a list as "list.field").
 *
 * @return array<string, array{string, array<string, string>}>
 */
function ttvCounterparts(): array
{
    return [
        'hero' => ['strook-1', ['label' => 'label', 'kop' => 'kop', 'tekst' => 'intro']],
        'cijfers' => ['strook-2', ['items' => 'cijfers', 'items.label' => 'cijfers.waarde', 'items.label_2' => 'cijfers.label']],
        'over-ons' => ['over-ons', [
            'label' => 'label', 'kop' => 'kop', 'tekst' => 'intro',
            'items' => 'kaarten', 'items.icoon' => 'kaarten.icoon', 'items.kop' => 'kaarten.titel', 'items.tekst' => 'kaarten.tekst',
        ]],
        'trainingstijden' => ['trainingstijden', [
            'label' => 'label', 'kop' => 'kop', 'tekst' => 'intro',
            'items' => 'avonden', 'items.kop' => 'avonden.dag', 'items.label_2' => 'avonden.tijd', 'items.lijst' => 'avonden.activiteiten',
            'tekst_2' => 'adres',
        ]],
        'teams' => ['teams', [
            'label' => 'label', 'kop' => 'kop', 'tekst' => 'intro',
            'items' => 'teams', 'items.link' => 'teams.uitslagen', 'items.kop' => 'teams.naam', 'items.tekst' => 'teams.competitie', 'items.lijst_2' => 'teams.spelers',
        ]],
        'locatie' => ['locatie', [
            'label' => 'label', 'kop' => 'kop', 'tekst' => 'intro', 'knop' => 'kaart_knop',
            'items' => 'blokken', 'items.icoon' => 'blokken.icoon', 'items.kop' => 'blokken.titel', 'items.tekst' => 'blokken.tekst',
        ]],
        'lid-worden' => ['lid-worden', ['label' => 'label', 'kop' => 'kop', 'tekst' => 'tekst', 'knop' => 'knop', 'knop_2' => 'tweede_knop']],
        'contact' => ['contact', ['label' => 'label', 'kop' => 'kop', 'tekst' => 'intro', 'label_2' => 'adres', 'knop' => 'email']],
    ];
}

/**
 * Where the classes differ, with the words of the limit in docs/scanresultaat.md that explains it.
 *
 * @return array{other_type: array<string, string>, only_by_hand: array<string, string>, only_scanned: array<string, string>}
 */
function ttvDifferences(): array
{
    $choice = 'Een keuze ontstaat alleen uit iconen';
    $row = 'Elementen op een rij met een eigen tag of een eigen icoon zijn losse velden';
    $paragraph = 'Een `p` is altijd lange tekst';
    $repeat = 'Wat binnen een item herhaalt';
    $shown = 'De scan leest alleen wat de pagina toont';
    $attribute = 'Een waarde die alleen in een attribuut staat';

    return [
        'other_type' => [
            'trainingstijden.avonden.dag' => $choice,
            'trainingstijden.avonden.activiteiten' => $repeat,
            'trainingstijden.adres' => $paragraph,
            'teams.teams.competitie' => $paragraph,
            'contact.email' => 'Een `mailto:`-link met tekst is een knop',
        ],
        'only_by_hand' => [
            'hero.kop_accent' => 'Een kop in twee kleuren',
            'hero.knoppen' => $row,
            'hero.knoppen.tekst' => $row,
            'hero.knoppen.gaat_naar' => $row,
            'hero.knoppen.stijl' => $choice,
            'cijfers.cijfers.achtervoegsel' => 'een getal met een teken erachter',
            'over-ons.anker' => $attribute,
            'trainingstijden.anker' => $attribute,
            'trainingstijden.avonden.kleur' => $choice,
            'teams.anker' => $attribute,
            'teams.teams.klasse' => $repeat,
            'teams.teams.poule' => $repeat,
            'teams.teams.reserves' => $shown,
            'locatie.anker' => $attribute,
            'locatie.kaart_alt' => 'Het veldtype `image` bestaat nog niet',
            'lid-worden.anker' => $attribute,
            'contact.anker' => $attribute,
            'contact.bedankt_kop' => $shown,
            'contact.bedankt_tekst' => $shown,
        ],
        'only_scanned' => [
            'strook-1.knop' => $row,
            'strook-1.knop_2' => $row,
            'trainingstijden.items.label' => 'tekst die de site zelf berekent',
            'teams.items.lijst' => $repeat,
        ],
    ];
}

/**
 * The fields of a strip type with their type, a field of a list as "list.field".
 *
 * @return array<string, string>
 */
function ttvFieldTypes(StripType $type): array
{
    $types = [];

    foreach ($type->fields() as $field) {
        $types[$field->getKey()] = $field->type();

        foreach ($field instanceof Items ? $field->getFields() : [] as $item) {
            $types[$field->getKey().'.'.$item->getKey()] = $item->type();
        }
    }

    return $types;
}

it('writes a class for every strip type the developer wrote, in the order of the page', function (): void {
    $result = GenerateStripTypes::run(ttvScan());
    $counterparts = ttvCounterparts();

    expect($result->skipped)->toBe([])
        ->and(array_map(static fn (GeneratedStripType $type): string => $type->key, $result->written))->toBe(array_column($counterparts, 0))
        ->and(array_keys($counterparts))->toBe(array_column(ttvContent()['strips'], 'type'))
        ->and(array_keys(array_filter($counterparts, static fn (array $counterpart, string $key): bool => $counterpart[0] === $key, ARRAY_FILTER_USE_BOTH)))
        ->toHaveCount(6);
});

it('writes the fields and field types of the developer, apart from what the scan cannot see', function (): void {
    $written = writtenStripTypes(GenerateStripTypes::run(ttvScan()));
    $handwritten = ttvStripTypes();
    $sameType = 0;
    $otherType = [];
    $onlyByHand = [];
    $onlyScanned = [];

    foreach (ttvCounterparts() as $key => [$scanned, $fields]) {
        $byHand = ttvFieldTypes($handwritten->find($key));
        $proposed = ttvFieldTypes($written->find($scanned));

        foreach ($fields as $field => $counterpart) {
            expect($proposed)->toHaveKey($field)
                ->and($byHand)->toHaveKey($counterpart);

            if ($proposed[$field] === $byHand[$counterpart]) {
                $sameType++;
            } else {
                $otherType[] = $key.'.'.$counterpart;
            }
        }

        foreach (array_diff(array_keys($byHand), $fields) as $field) {
            $onlyByHand[] = $key.'.'.$field;
        }

        foreach (array_diff(array_keys($proposed), array_keys($fields)) as $field) {
            $onlyScanned[] = $scanned.'.'.$field;
        }
    }

    $differences = ttvDifferences();

    expect($sameType)->toBe(42)
        ->and($otherType)->toBe(array_keys($differences['other_type']))
        ->and($onlyByHand)->toBe(array_keys($differences['only_by_hand']))
        ->and($onlyScanned)->toBe(array_keys($differences['only_scanned']));
});

/**
 * The default level of every heading of a strip type, a heading of a list as "list.field".
 *
 * @return array<string, string>
 */
function ttvHeadingLevels(StripType $type): array
{
    $levels = [];

    foreach ($type->fields() as $field) {
        $fields = $field instanceof Items
            ? array_map(static fn (Field $item): array => [$field->getKey().'.'.$item->getKey(), $item], $field->getFields())
            : [[$field->getKey(), $field]];

        foreach ($fields as [$path, $heading]) {
            if ($heading instanceof Heading) {
                $levels[$path] = $heading->getTag();
            }
        }
    }

    return $levels;
}

it('writes every heading at the level the developer gave it', function (): void {
    $written = writtenStripTypes(GenerateStripTypes::run(ttvScan()));
    $handwritten = ttvStripTypes();
    $headings = 0;

    foreach (ttvCounterparts() as $key => [$scanned, $fields]) {
        $byHand = ttvHeadingLevels($handwritten->find($key));

        foreach (ttvHeadingLevels($written->find($scanned)) as $field => $level) {
            if (isset($byHand[$fields[$field] ?? ''])) {
                expect($level)->toBe($byHand[$fields[$field]], $key.'.'.$field);
                $headings++;
            }
        }
    }

    expect($headings)->toBe(10);
});

it('explains every difference with a limit that the documentation of the scan names', function (): void {
    $document = (string) file_get_contents(__DIR__.'/../../../../docs/scanresultaat.md');

    foreach (ttvDifferences() as $differences) {
        foreach (array_unique($differences) as $limit) {
            expect($document)->toContain($limit);
        }
    }
});
