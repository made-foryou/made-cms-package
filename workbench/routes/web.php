<?php

use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Content\Models\Page;
use Made\CmsPackage\Content\Models\Strip;

Route::get('/', function () {
    return view('welcome');
});

// A host page built from the strips of the home page, to try the edit mode: log in through the tile and see the outlines.
Route::get('voorbeeld', function () {
    $page = Page::query()->forPath('/')->firstOrFail();

    $strips = $page->strips->map(fn (Strip $strip): string => sprintf(
        '<section data-made-cms-strip="%d" style="padding: 72px 24px; border-bottom: 1px solid #e3e8f5"><h2 style="margin: 0 0 12px">%s</h2><p style="margin: 0">%s</p></section>',
        $strip->id,
        e((string) ($strip->content['title'] ?? '')),
        e((string) ($strip->content['intro'] ?? '')),
    ))->implode('');

    return response('<!DOCTYPE html><html lang="nl"><head><meta charset="utf-8"><title>Voorbeeld</title></head><body style="margin: 0; font-family: system-ui, sans-serif">'.$strips.'</body></html>');
});
