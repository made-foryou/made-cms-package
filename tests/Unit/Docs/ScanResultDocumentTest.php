<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\ScanResult;
use Made\CmsPackage\Content\Enums\ScanDoubt;
use Made\CmsPackage\Content\Enums\ScanRole;

function scanResultDocument(): string
{
    return (string) file_get_contents(dirname(__DIR__, 3).'/docs/scanresultaat.md');
}

function scanResultDocumentPart(string $name): string
{
    return (string) preg_replace('/^.*<!-- '.$name.':start -->(.*)<!-- '.$name.':end -->.*$/s', '$1', scanResultDocument());
}

it('shows an example that the result reads and writes unchanged', function (): void {
    $json = (string) preg_replace('/^.*```json\s*(.*?)```.*$/s', '$1', scanResultDocumentPart('example'));
    $example = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($example)->toBeArray()
        ->and(ScanResult::fromArray($example)->toArray())->toBe($example);
});

it('explains every doubt, in the order of the cases', function (): void {
    preg_match_all('/^\|\s*`([a-z_]+)`/m', scanResultDocumentPart('doubts'), $matches);

    expect($matches[1])->toBe(array_map(static fn (ScanDoubt $doubt): string => $doubt->value, ScanDoubt::cases()));
});

it('describes every field type the scan proposes', function (): void {
    foreach (array_unique(array_map(static fn (ScanRole $role): string => $role->type(), ScanRole::cases())) as $type) {
        expect(scanResultDocument())->toContain('| `'.$type.'` |');
    }
});
