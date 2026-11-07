<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Actions\GenerateStripTypes;
use Made\CmsPackage\Content\Data\GeneratedStripType;
use Made\CmsPackage\Content\Data\GeneratedStripTypes;
use Made\CmsPackage\Content\Data\ScanField;
use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Data\ScanType;
use Made\CmsPackage\Content\Data\SkippedStripType;
use Made\CmsPackage\Content\Enums\StripTypeSkip;
use Made\CmsPackage\Content\Exceptions\NotLocalEnvironment;
use Made\CmsPackage\Content\Scan\ScannedStripType;
use Made\CmsPackage\Content\Strips\StripType;

beforeEach(function (): void {
    $this->app->detectEnvironment(static fn (): string => 'local');

    ['folder' => $this->folder, 'namespace' => $this->namespace] = useStripFolder();
});

/**
 * @param  list<ScanField>|null  $fields
 */
function proposedType(string $key, string $label = 'Strook', ?array $fields = null): ScanType
{
    return new ScanType($key, $label, null, 'fingerprint', 1, $fields ?? [new ScanField('kop', 'text', 'h2')]);
}

function generate(ScanType ...$types): GeneratedStripTypes
{
    return GenerateStripTypes::run(new ScanResult(array_values($types), []));
}

/**
 * @return list<array{string, string}>
 */
function skippedTypes(GeneratedStripTypes $result): array
{
    return array_map(static fn (SkippedStripType $type): array => [$type->key, $type->reason->value], $result->skipped);
}

it('writes a class for every strip type in the folder where the catalogue finds it', function (): void {
    $result = generate(proposedType('over-ons', 'Over ons'), proposedType('hero', 'Hero'));

    expect(array_map(static fn (GeneratedStripType $type): array => [$type->key, $type->class, $type->path], $result->written))->toBe([
        ['over-ons', $this->namespace.'\\OverOns', $this->folder.'/OverOns.php'],
        ['hero', $this->namespace.'\\Hero', $this->folder.'/Hero.php'],
    ])
        ->and($result->skipped)->toBe([])
        ->and(array_map(static fn (StripType $type): array => [$type->key(), $type->label(), $type::class], writtenStripTypes($result)->all()))->toBe([
            ['hero', 'Hero', $this->namespace.'\\Hero'],
            ['over-ons', 'Over ons', $this->namespace.'\\OverOns'],
        ]);
});

it('writes exactly the strip types it proposes', function (): void {
    $scan = ttvScan();
    $types = writtenStripTypes(GenerateStripTypes::run($scan));

    expect($types->all())->toHaveCount(8);

    foreach ($scan->types as $type) {
        expect($types->find($type->key)->describe())->toBe(ScannedStripType::from($type)->describe());
    }
});

it('never overwrites a class that is there, also not under other capitals', function (): void {
    mkdir($this->folder);
    file_put_contents($this->folder.'/Overons.php', '<?php // the developer\'s own');

    $result = generate(proposedType('over-ons'), proposedType('hero'));

    expect(skippedTypes($result))->toBe([['over-ons', 'class_exists']])
        ->and($result->skipped[0]->class)->toBe($this->namespace.'\\OverOns')
        ->and(array_map(static fn (GeneratedStripType $type): string => $type->key, $result->written))->toBe(['hero'])
        ->and(file_get_contents($this->folder.'/Overons.php'))->toBe('<?php // the developer\'s own')
        ->and(array_values(array_diff(scandir($this->folder) ?: [], ['.', '..'])))->toBe(['Hero.php', 'Overons.php']);
});

it('skips a strip type whose key the catalogue already has', function (): void {
    useStripTypes();

    $result = generate(proposedType('hero'), proposedType('cards'), proposedType('quote'));

    expect(skippedTypes($result))->toBe([['hero', 'key_exists'], ['cards', 'key_exists']])
        ->and(is_file($this->folder.'/Hero.php'))->toBeFalse()
        ->and(is_file($this->folder.'/Quote.php'))->toBeTrue();
});

it('skips a key or a class name that comes back in the same scan', function (): void {
    $result = generate(proposedType('lid-worden'), proposedType('lid-worden'), proposedType('lidworden'));

    expect(skippedTypes($result))->toBe([['lid-worden', 'key_exists'], ['lidworden', 'class_exists']])
        ->and(array_values(array_diff(scandir($this->folder) ?: [], ['.', '..'])))->toBe(['LidWorden.php']);
});

it('skips a strip type the catalogue would refuse, so the site keeps working', function (): void {
    $result = generate(
        proposedType('Over ons'),
        proposedType('hero', 'Hero', [new ScanField('icoon', 'choice', 'svg')]),
        proposedType('quote'),
    );

    expect(skippedTypes($result))->toBe([['Over ons', 'invalid'], ['hero', 'invalid']])
        ->and($result->skipped[0]->detail)->toBe("Strip type [{$this->namespace}\\OverOns] has an invalid key [Over ons]; use lowercase letters, digits and dashes.")
        ->and($result->skipped[1]->detail)->toBe("Strip type [{$this->namespace}\\Hero] field [icoon] has no options.")
        ->and(StripTypeSkip::Invalid->label())->toBe('De definitie klopt niet.')
        ->and(array_values(array_diff(scandir($this->folder) ?: [], ['.', '..'])))->toBe(['Quote.php']);
});

it('names the fields it leaves out until the image field exists', function (): void {
    $result = generate(proposedType('hero', 'Hero', [new ScanField('afbeelding', 'image', 'img'), new ScanField('kop', 'text', 'h1')]));

    expect($result->written[0]->leftOut)->toBe(['afbeelding'])
        ->and((string) file_get_contents($this->folder.'/Hero.php'))->toContain('// afbeelding: wacht op het veldtype Afbeelding.');
});

it('writes nothing outside the local environment', function (string $environment): void {
    $this->app->detectEnvironment(static fn (): string => $environment);

    try {
        expect(static fn (): GeneratedStripTypes => generate(proposedType('hero')))->toThrow(NotLocalEnvironment::class);
    } finally {
        // Tearing down in production would ask to confirm the migrations.
        $this->app->detectEnvironment(static fn (): string => 'testing');
    }

    expect(file_exists($this->folder))->toBeFalse();
})->with(['production', 'staging', 'testing']);
