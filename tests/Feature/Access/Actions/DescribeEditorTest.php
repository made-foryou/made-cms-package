<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Actions\DescribeEditor;
use Made\CmsPackage\Access\Models\Editor;

it('describes an editor by first name and initial', function (string $name, string $firstName, string $initial): void {
    $profile = DescribeEditor::run(Editor::factory()->make(['name' => $name, 'email' => 'menno@made-foryou.nl']));

    expect($profile->firstName)->toBe($firstName)
        ->and($profile->initial)->toBe($initial);
})->with([
    'full name' => ['Menno Tempelaar', 'Menno', 'M'],
    'single name' => ['menno', 'menno', 'M'],
    'extra spaces' => ['  Émile   Zola ', 'Émile', 'É'],
]);

it('falls back to the e-mail address for an editor without a name', function (): void {
    $profile = DescribeEditor::run(Editor::factory()->make(['name' => ' ', 'email' => 'menno@made-foryou.nl']));

    expect($profile->firstName)->toBeNull()
        ->and($profile->initial)->toBe('M');
});
