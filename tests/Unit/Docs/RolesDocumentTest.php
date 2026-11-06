<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Enums\Screen;

/**
 * The matrix in docs/rollen-en-schermen.md: one row per screen, one column per role. A ✓ is a screen the role opens
 * in Role::screens(); a ○ is decided but not built, so the role does not open it yet.
 *
 * @return array{roles: list<string>, rows: array<string, list<string>>}
 */
function rolesDocumentMatrix(): array
{
    $document = (string) file_get_contents(dirname(__DIR__, 3).'/docs/rollen-en-schermen.md');
    $matrix = (string) preg_replace('/^.*<!-- matrix:start -->(.*)<!-- matrix:end -->.*$/s', '$1', $document);
    $lines = array_values(array_filter(
        array_map(trim(...), explode("\n", $matrix)),
        fn (string $line): bool => str_starts_with($line, '|') && ! preg_match('/^\|[\s:|-]+\|$/', $line),
    ));
    $cells = fn (string $line): array => array_map(trim(...), explode('|', trim($line, '|')));

    $header = $cells(array_shift($lines));
    $rows = [];

    foreach ($lines as $line) {
        $row = $cells($line);
        preg_match('/`([a-z-]+)`/', $row[0], $match);
        $rows[$match[1] ?? $row[0]] = $row;
    }

    return ['roles' => array_slice($header, 1, count(Role::cases())), 'rows' => $rows];
}

it('has a column per role, in the order of the cases', function (): void {
    expect(rolesDocumentMatrix()['roles'])
        ->toBe(array_map(fn (Role $role): string => $role->label(), Role::cases()));
});

it('has a row per screen, in the order of the cases', function (): void {
    expect(array_keys(rolesDocumentMatrix()['rows']))
        ->toBe(array_map(fn (Screen $screen): string => $screen->value, Screen::cases()));
});

it('ticks exactly the screens each role opens', function (Role $role): void {
    $column = array_search($role, Role::cases(), true) + 1;
    $ticked = array_keys(array_filter(
        rolesDocumentMatrix()['rows'],
        fn (array $row): bool => str_starts_with($row[$column], '✓'),
    ));

    expect($ticked)->toBe(array_map(fn (Screen $screen): string => $screen->value, $role->screens()));
})->with(Role::cases());
