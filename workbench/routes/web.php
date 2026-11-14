<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Content\Models\MediaFolder;
use Made\CmsPackage\Content\Models\Page;

// The upload field of the CMS on a page of its own, as a host includes it: no screen of the package shows it yet.
// Log in as an editor first; ?folder= sends the files into that folder, ?bijsnijden=1 hands the photos of a batch to
// the crop step, as the media library will (#55).
Route::get('werkbank/uploadveld', fn (Request $request) => view('upload-field', [
    'folder' => MediaFolder::query()->find($request->integer('folder')),
    'crop' => $request->boolean('bijsnijden'),
]));

// Routes of the site go above this line: the CMS pages take every address that is left.
Route::madeCms(fn (Page $page) => view('page', ['page' => $page]));
