<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Models\Editor;

it('finds an editor by e-mail address regardless of case and surrounding spaces', function (): void {
    $editor = Editor::factory()->create(['email' => 'menno@made-foryou.nl']);
    Editor::factory()->create();

    expect(Editor::query()->forEmail(' Menno@Made-ForYou.nl ')->sole()->is($editor))->toBeTrue();
});

it('finds nothing for an unknown e-mail address', function (): void {
    Editor::factory()->create(['email' => 'menno@made-foryou.nl']);

    expect(Editor::query()->forEmail('iemand@example.com')->exists())->toBeFalse();
});
