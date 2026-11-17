<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\NameEditors;
use Made\CmsPackage\Access\Models\Editor;

it('names the editors by their id, leaving out an editor that is gone', function (): void {
    $sanne = Editor::factory()->create(['name' => 'Sanne de Vries']);
    $menno = Editor::factory()->create(['name' => 'Menno Tempelaar']);

    $names = NameEditors::run([$menno->id, $sanne->id, $menno->id, 999_999]);
    ksort($names);

    expect($names)->toBe([
        $sanne->id => 'Sanne de Vries',
        $menno->id => 'Menno Tempelaar',
    ]);
});

it('asks nothing without ids', function (): void {
    expect(NameEditors::run([]))->toBe([]);
});
