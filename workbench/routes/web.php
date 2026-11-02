<?php

use Illuminate\Support\Facades\Route;
use Made\CmsPackage\Content\Models\Page;

// Routes of the site go above this line: the CMS pages take every address that is left.
Route::madeCms(fn (Page $page) => view('page', ['page' => $page]));
