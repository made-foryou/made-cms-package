<?php

declare(strict_types=1);

use Made\CmsPackage\Access\Enums\Role;
use Made\CmsPackage\Access\Models\Editor;
use Made\CmsPackage\Content\Models\Media;
use Made\CmsPackage\Content\Models\MediaUsage;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    $this->actingAs(Editor::factory()->create(), 'made-cms');
});

it('saves the share image of a page and goes back to its card', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->at('/over-ons')->create();

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id.'/share-image', ['share_image' => (string) $media->id])
        ->assertRedirect('/made-cms/pages/'.$page->id.'#made-cms-share')
        ->assertSessionHasNoErrors();

    expect($page->refresh()->share_image_id)->toBe($media->id)
        ->and(MediaUsage::query()->where('media_id', $media->id)->exists())->toBeTrue();
});

it('takes the share image away for an empty choice', function (string $value): void {
    $media = Media::factory()->create();
    $page = Page::factory()->withShareImage($media)->at('/over-ons')->create();

    $this->put('made-cms/pages/'.$page->id.'/share-image', ['share_image' => $value])
        ->assertRedirect('/made-cms/pages/'.$page->id.'#made-cms-share');

    expect($page->refresh()->share_image_id)->toBeNull();
})->with(['empty' => [''], 'blank' => ['  ']]);

it('refuses a choice that is no id of a file, back at the card', function (mixed $value): void {
    $page = Page::factory()->at('/over-ons')->create();

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id.'/share-image', ['share_image' => $value])
        ->assertRedirect('/made-cms/pages/'.$page->id.'#made-cms-share')
        ->assertSessionHasErrors(['share_image' => 'Kies een afbeelding uit Media.']);

    expect($page->refresh()->share_image_id)->toBeNull();
})->with(['text' => ['teamfoto'], 'zero' => ['0'], 'negative' => ['-3'], 'a list' => [[1, 2]]]);

it('refuses a request without a choice', function (): void {
    $page = Page::factory()->at('/over-ons')->create();

    $this->put('made-cms/pages/'.$page->id.'/share-image', [])
        ->assertSessionHasErrors(['share_image' => 'Kies een afbeelding uit Media.']);
});

it('refuses a file a shared link cannot show, back at the card, and keeps what the page had', function (Closure $file, string $error): void {
    $kept = Media::factory()->create();
    $page = Page::factory()->withShareImage($kept)->at('/over-ons')->create();

    $this->from('/made-cms/pages/'.$page->id)
        ->put('made-cms/pages/'.$page->id.'/share-image', ['share_image' => (string) $file()])
        ->assertRedirect('/made-cms/pages/'.$page->id.'#made-cms-share')
        ->assertSessionHasErrors(['share_image' => $error]);

    expect($page->refresh()->share_image_id)->toBe($kept->id);
})->with([
    'a file that is gone' => [fn (): int => missingId(), 'Deze afbeelding staat niet meer in Media. Kies een andere.'],
    'a document' => [fn (): int => Media::factory()->document()->create()->id, 'Dit bestand is geen afbeelding. Kies een foto.'],
    'a drawing' => [fn (): int => Media::factory()->create(['mime' => 'image/svg+xml'])->id, 'Apps tonen geen svg bij een gedeelde link. Kies een foto, bijvoorbeeld jpg, png of webp.'],
]);

it('opens for every editor who opens the pages', function (Role $role, int $status): void {
    $media = Media::factory()->create();
    $page = Page::factory()->at('/over-ons')->create();

    $this->actingAs(Editor::factory()->withRoles($role)->create(), 'made-cms')
        ->put('made-cms/pages/'.$page->id.'/share-image', ['share_image' => (string) $media->id])
        ->assertStatus($status);
})->with([
    'admin' => [Role::Admin, 302],
    'content manager' => [Role::ContentManager, 302],
    'seo specialist' => [Role::SeoSpecialist, 302],
    'owner' => [Role::Owner, 302],
]);

it('sends a visitor without a session to the login', function (): void {
    auth('made-cms')->logout();
    $media = Media::factory()->create();
    $page = Page::factory()->at('/over-ons')->create();

    $this->put('made-cms/pages/'.$page->id.'/share-image', ['share_image' => (string) $media->id])
        ->assertRedirect(route('made-cms-package.login'));

    expect($page->refresh()->share_image_id)->toBeNull();
});

it('answers 404 for a page in the bin', function (): void {
    $media = Media::factory()->create();
    $page = Page::factory()->create(['deleted_at' => now()]);

    $this->put('made-cms/pages/'.$page->id.'/share-image', ['share_image' => (string) $media->id])->assertNotFound();
});
