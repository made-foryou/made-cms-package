<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Content\Models\Page;

beforeEach(function (): void {
    app('view')->addLocation(__DIR__.'/../../../workbench/resources/views');

    Route::middleware('web')->group(__DIR__.'/../../../workbench/routes/web.php');
});

it('serves the published pages of the workbench through the macro', function (): void {
    Page::factory()->published()->at('/')->withStrips([storedStrip('hero', ['kop' => 'Welkom'])])->create(['title' => 'Home']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<title>Home · Made CMS Workbench</title>', false)
        ->assertSee('data-cms-strip=', false)
        ->assertSeeText('Welkom');
});

it('answers 404 for a draft page of the workbench', function (): void {
    Page::factory()->at('/concept')->create();

    $this->get('concept')->assertNotFound();
});
