<?php

declare(strict_types=1);

use Dom\HTMLDocument;
use Illuminate\Support\Facades\Storage;
use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaFolder;

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'app.name' => 'TTV Merwestad']);
    Storage::fake('public');
});

function mediaLibrary(string $query = '', Role $role = Role::ContentManager): string
{
    return (string) test()->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->get('made-cms/media'.($query === '' ? '' : '?'.$query))
        ->assertOk()
        ->getContent();
}

/**
 * The config the list of the library hands its script.
 *
 * @return array<string, mixed>
 */
function mediaLibraryConfig(string $html): array
{
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);

    /** @var array<string, mixed> */
    return json_decode((string) $document->querySelector('[data-media]')?->getAttribute('data-config'), true, flags: JSON_THROW_ON_ERROR);
}

it('opens the media library at its own address', function (): void {
    expect(route('made-cms-package.screens.media', [], false))->toBe('/made-cms/media');
});

it('opens for the roles that open the media library and for no other', function (Role $role, bool $opens): void {
    $response = $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')->get('made-cms/media');

    if (! $opens) {
        $response->assertForbidden();

        return;
    }

    expect((string) $response->assertOk()->getContent())->toContain('<h1 id="made-cms-titel">Media</h1>')
        ->not->toContain('wordt nog gebouwd');
})->with([
    'admin' => [Role::Admin, true],
    'webmaster' => [Role::Webmaster, true],
    'content manager' => [Role::ContentManager, true],
    'seo specialist' => [Role::SeoSpecialist, false],
    'owner' => [Role::Owner, false],
]);

it('sends a guest to the login page', function (): void {
    $this->get('made-cms/media')->assertRedirect(route('made-cms-package.login'));
});

it('is never stored by a cache, framed or indexed', function (): void {
    $response = $this->actingAs(Editor::factory()->create(), 'made-cms')
        ->get('made-cms/media')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

it('introduces the screen, marks it in the menu and offers to upload', function (): void {
    $html = mediaLibrary();

    expect($html)->toContain('<span class="eyebrow">Verzamelingen</span>')
        ->toContain('Alle foto’s, documenten en video’s van je site op één plek.')
        ->toContain('data-slot="library-upload"')
        ->and(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and($html)->toContain('href="/made-cms/media" aria-current="page"><span class="merk" aria-hidden="true"></span>Media</a>');
});

it('hands the script the first page of files and the folders as their endpoints give them', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    Media::factory()->in($teams)->count(2)->create();
    Media::factory()->document()->create();
    $editor = Editor::factory()->create();

    $config = mediaLibraryConfig((string) $this->actingAs($editor, 'made-cms')->get('made-cms/media')->getContent());

    expect($config['library'])->toBeTrue()
        ->and($config['filter'])->toBe(['kind' => null, 'without_alt' => false, 'q' => '', 'sort' => 'newest'])
        ->and($config['chosen'])->toBeNull()
        ->and($config['initial']['files'])->toBe($this->getJson('made-cms/media/files')->json())
        ->and($config['initial']['folders'])->toBe($this->getJson('made-cms/media/folders')->json());
});

it('opens with the folder, the filter, the search and the order of its address', function (): void {
    $teams = MediaFolder::factory()->create(['name' => 'Teams']);
    $schema = Media::factory()->in($teams)->document()->create(['name' => 'wedstrijdschema']);
    Media::factory()->in($teams)->create(['name' => 'wedstrijd']);
    Media::factory()->document()->create(['name' => 'wedstrijdverslag']);

    $config = mediaLibraryConfig(mediaLibrary('folder='.$teams->id.'&kind=document&q=wedstrijd&sort=name&file='.$schema->id));

    expect($config['folder'])->toBe(['id' => $teams->id, 'name' => 'Teams'])
        ->and($config['filter'])->toBe(['kind' => 'document', 'without_alt' => false, 'q' => 'wedstrijd', 'sort' => 'name'])
        ->and($config['chosen'])->toBe($schema->id)
        ->and(array_column($config['initial']['files']['files'], 'id'))->toBe([$schema->id])
        ->and($config['initial']['files']['summary'])->toBe('1 document voor “wedstrijd”')
        ->and($config['initial']['folders']['folders'][0]['count'])->toBe(1);
});

it('lists the images without an alt text, whatever kind the address asks for', function (): void {
    $without = Media::factory()->create();
    Media::factory()->withAlt('Het eerste team')->create();
    Media::factory()->document()->create();

    $config = mediaLibraryConfig(mediaLibrary('kind=document&without_alt=1'));

    expect($config['filter'])->toMatchArray(['kind' => null, 'without_alt' => true])
        ->and(array_column($config['initial']['files']['files'], 'id'))->toBe([$without->id])
        ->and($config['initial']['files']['summary'])->toBe('1 afbeelding zonder alt-tekst');
});

it('opens in all files when the folder of its address is gone, and leaves out what it does not know', function (string $query): void {
    Media::factory()->count(2)->create();

    $config = mediaLibraryConfig(mediaLibrary($query));

    expect($config['folder'])->toBeNull()
        ->and($config['filter'])->toBe(['kind' => null, 'without_alt' => false, 'q' => '', 'sort' => 'newest'])
        ->and($config['chosen'])->toBeNull()
        ->and($config['initial']['files']['total'])->toBe(2);
})->with([
    'a folder that is gone' => ['folder=404'],
    'no number' => ['folder=teams&file=foto'],
    'an unknown kind and order' => ['kind=foto&sort=random'],
    'lists' => ['folder[]=1&kind[]=image&q[]=x&sort[]=name&file[]=1'],
]);

it('puts the upload field, which hands photos to the crop step, and the crop step with folders on the screen', function (): void {
    $document = HTMLDocument::createFromString(mediaLibrary(), LIBXML_NOERROR);
    $upload = json_decode((string) $document->querySelector('[data-media] [data-upload]')?->getAttribute('data-config'), true, flags: JSON_THROW_ON_ERROR);

    expect($document->querySelector('[data-media] [data-upload]')?->hasAttribute('data-one-image'))->toBeFalse()
        ->and($upload['crop'])->toBeTrue()
        ->and($document->querySelector('dialog[data-media-crop] [data-slot="crop-folder"]'))->not->toBeNull();
});

it('loads the scripts of the upload field, the list and the crop step', function (): void {
    $html = mediaLibrary();

    expect($html)->toMatch('#<script type="module" src="[^"]*/upload\.js\?v=[^"]*"#')
        ->toMatch('#<script type="module" src="[^"]*/media\.js\?v=[^"]*"#')
        ->toMatch('#<script type="module" src="[^"]*/crop\.js\?v=[^"]*"#');
});

it('speaks English when the launcher does', function (): void {
    config(['made-cms-package.launcher.locale' => 'en']);

    expect(mediaLibrary())->toContain('<span class="eyebrow">Collections</span>')
        ->toContain('Every photo, document and video of your site in one place.');
});

it('tells its scripts where a file is replaced, put back, saved and deleted', function (): void {
    $html = mediaLibrary();
    $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
    /** @var array{urls: array<string, string>} $library */
    $library = json_decode((string) $document->querySelector('[data-media-library]')?->getAttribute('data-config'), true, flags: JSON_THROW_ON_ERROR);

    expect($library['urls'])->toMatchArray([
        'replace' => '/made-cms/media/:media/replace',
        'restore' => '/made-cms/media/:media/restore',
        'download' => '/made-cms/media/:media/download',
        'delete' => '/made-cms/media/delete',
    ])->and(mediaLibraryConfig($html)['urls']['delete'] ?? null)->toBe('/made-cms/media/delete');
});
