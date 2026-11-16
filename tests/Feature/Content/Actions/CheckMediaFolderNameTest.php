<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\CheckMediaFolderName;
use Made\CmsPackage\Content\Models\MediaFolder;

/**
 * @return array<string, list<string>>
 */
function folderNameErrors(string $name, ?MediaFolder $except = null): array
{
    try {
        CheckMediaFolderName::run($name, $except);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('gives the name trimmed and with single spaces', function (): void {
    expect(CheckMediaFolderName::run("  Club   avond \t 2026 "))->toBe('Club avond 2026');
});

it('refuses an empty name', function (string $name): void {
    expect(folderNameErrors($name))->toBe(['name' => ['Geef de map een naam.']]);
})->with(['', '   ', "\t\n"]);

it('refuses a name longer than the chip holds', function (): void {
    expect(CheckMediaFolderName::run(str_repeat('é', CheckMediaFolderName::MAX_LENGTH)))->toBe(str_repeat('é', 60))
        ->and(folderNameErrors(str_repeat('a', CheckMediaFolderName::MAX_LENGTH + 1)))
        ->toBe(['name' => ['Een mapnaam mag hoogstens 60 tekens zijn.']]);
});

it('refuses a name another folder has, whatever its capitals', function (string $name): void {
    MediaFolder::factory()->create(['name' => 'Teams']);

    expect(folderNameErrors($name))->toBe(['name' => ['Er is al een map Teams.']]);
})->with(['Teams', 'teams', ' TEAMS ']);

it('takes the name the folder itself has', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);

    expect(CheckMediaFolderName::run('teams', $teams))->toBe('teams')
        ->and(folderNameErrors('Teams', MediaFolder::factory()->create()))->toBe(['name' => ['Er is al een map Teams.']]);
});
