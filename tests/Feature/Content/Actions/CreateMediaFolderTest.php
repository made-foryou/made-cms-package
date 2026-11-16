<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Made\CmsPackage\Content\Actions\CreateMediaFolder;
use Made\CmsPackage\Content\Events\MediaFolderCreated;
use Made\CmsPackage\Content\Models\MediaFolder;

it('creates a folder with its name trimmed and with single spaces', function (): void {
    $folder = CreateMediaFolder::run('  Club   avond ');

    expect($folder->exists)->toBeTrue()
        ->and($folder->name)->toBe('Club avond')
        ->and(MediaFolder::query()->pluck('name')->all())->toBe(['Club avond']);
});

it('tells that a folder was created', function (): void {
    Event::fake([MediaFolderCreated::class]);

    $folder = CreateMediaFolder::run('Teams');

    Event::assertDispatched(MediaFolderCreated::class, fn (MediaFolderCreated $event): bool => $event->folder->is($folder));
});

it('refuses a name another folder has and creates nothing', function (): void {
    Event::fake([MediaFolderCreated::class]);
    MediaFolder::factory()->create(['name' => 'Teams']);

    try {
        CreateMediaFolder::run('teams');
        test()->fail('The folder was created.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['name' => ['Er is al een map Teams.']]);
    }

    expect(MediaFolder::query()->count())->toBe(1);
    Event::assertNotDispatched(MediaFolderCreated::class);
});

it('refuses a name a folder got at the same moment, as a taken name', function (): void {
    Event::fake([MediaFolderCreated::class]);
    MediaFolder::creating(static function (): void {
        DB::table('cms_media_folders')->insert(['name' => 'Teams', 'created_at' => now(), 'updated_at' => now()]);
    });

    try {
        CreateMediaFolder::run('Teams');
        test()->fail('The folder was created.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['name' => ['Er is al een map Teams.']]);
    }

    expect(MediaFolder::query()->pluck('name')->all())->toBe(['Teams']);
    Event::assertNotDispatched(MediaFolderCreated::class);
});
